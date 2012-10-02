var paraview;
var midas = midas || {};
midas.visualize = midas.visualize || {};

midas.visualize.left = {points: []};
midas.visualize.right = {points: []};

midas.visualize.start = function () {
    // Create a paraview proxy
    var file = json.visualize.url;

    if(typeof Paraview != 'function') {
        alert('Paraview javascript was not fetched correctly from server.');
        return;
    }

    $('#leftLoadingStatus').html('Creating ParaView session on the server and loading plugins...');
    paraview = {};
    paraview.left = new Paraview('/PWService');
    paraview.left.errorListener = {
        manageError: function(error) {
            midas.createNotice('A ParaViewWeb error occurred; check the console for information', 4000, 'error');
            console.log(error);
            return true;
        }
    };
    $('#rightLoadingStatus').html('Creating ParaView session on the server and loading plugins...');
    paraview.right = new Paraview('/PWService');
    paraview.right.errorListener = {
        manageError: function(error) {
            midas.createNotice('A ParaViewWeb error occurred; check the console for information', 4000, 'error');
            console.log(error);
            return true;
        }
    };

    paraview.left.createSession("midas", "dual view left", "default");
    paraview.left.loadPlugins();
    paraview.right.createSession("midas", "dual view right", "default");
    paraview.right.loadPlugins();

    $('#leftLoadingStatus').html('Reading image data from files...');
    paraview.left.plugins.midascommon.AsyncOpenData(function (retVal) {
        midas.visualize._dataOpened('left', retVal)
    }, {
        filename: json.visualize.urls.left,
        otherMeshes: []
    });
    $('#rightLoadingStatus').html('Reading image data from files...');
    paraview.right.plugins.midascommon.AsyncOpenData(function (retVal) {
        midas.visualize._dataOpened('right', retVal)
    }, {
        filename: json.visualize.urls.right,
        otherMeshes: []
    });
    midas.visualize.pointColors = midas.visualize._generateColorList(8);
};

midas.visualize._dataOpened = function (side, retVal) {
    midas.visualize[side].input = retVal.input;
    midas.visualize[side].bounds = retVal.imageData.Bounds;

    midas.visualize[side].maxDim = Math.max(midas.visualize[side].bounds[1] - midas.visualize[side].bounds[0],
                                           midas.visualize[side].bounds[3] - midas.visualize[side].bounds[2],
                                           midas.visualize[side].bounds[5] - midas.visualize[side].bounds[4]);
    midas.visualize[side].minVal = retVal.imageData.PointData.Arrays[0].Ranges[0][0];
    midas.visualize[side].maxVal = retVal.imageData.PointData.Arrays[0].Ranges[0][1];
    midas.visualize[side].imageWindow = [midas.visualize[side].minVal, midas.visualize[side].maxVal];

    midas.visualize[side].midI = (midas.visualize[side].bounds[0] + midas.visualize[side].bounds[1]) / 2.0;
    midas.visualize[side].midJ = (midas.visualize[side].bounds[2] + midas.visualize[side].bounds[3]) / 2.0;
    midas.visualize[side].midK = Math.floor((midas.visualize[side].bounds[4] + midas.visualize[side].bounds[5]) / 2.0);

    if(midas.visualize[side].bounds.length != 6) {
        console.log('Invalid image bounds ('+side+' image):');
        console.log(midas.visualize[side].bounds);
        return;
    }
    
    midas.visualize[side].defaultColorMap = [
       midas.visualize[side].minVal, 0.0, 0.0, 0.0,
       midas.visualize[side].maxVal, 1.0, 1.0, 1.0];
    midas.visualize[side].colorMap = midas.visualize[side].defaultColorMap;
    midas.visualize.currentSlice = midas.visualize[side].midK;
    midas.visualize.sliceMode = 'XY Plane';

    var params = {
        cameraFocalPoint: [midas.visualize[side].midI, midas.visualize[side].midJ, midas.visualize[side].midK],
        cameraPosition: [midas.visualize[side].midI, midas.visualize[side].midJ, midas.visualize[side].bounds[4] - 10],
        colorMap: midas.visualize[side].defaultColorMap,
        colorArrayName: json.visualize.colorArrayNames[side],
        sliceVal: midas.visualize.currentSlice,
        sliceMode: midas.visualize.sliceMode,
        parallelScale: Math.max(midas.visualize[side].bounds[1] - midas.visualize[side].bounds[0],
                                midas.visualize[side].bounds[3] - midas.visualize[side].bounds[2]) / 2.0,
        cameraUp: [0.0, -1.0, 0.0]
    };
    $('#'+side+'LoadingStatus').html('Initializing view state and renderer...');

    paraview[side].plugins.midasdual.AsyncInitViewState(function (retVal) {
        midas.visualize.initCallback(side, retVal)
    }, params);
};

midas.visualize.initCallback = function (side, retVal) {
    midas.visualize[side].lookupTable = retVal.lookupTable;
    midas.visualize[side].activeView = retVal.activeView;

    midas.visualize.switchRenderer(side); // render in the div
    $('img.'+side+'Loading').hide();
    $('#'+side+'LoadingStatus').html('').hide();
    $('#'+side+'Renderer').show();
    midas.visualize.disableMouseInteraction(side);

    if(side == 'left') { //sliders will be based on left image
        midas.visualize.setupSliders();
        midas.visualize.updateSliceInfo(midas.visualize.left.midK);
        midas.visualize.updateWindowInfo([midas.visualize.left.minVal, midas.visualize.left.maxVal]);
    }

    midas.visualize.enableActions(side, json.visualize.operations.split(';'));

    if(typeof midas.visualize.postInitCallback == 'function') {
        midas.visualize.postInitCallback(side);
    }

    midas.visualize[side].renderer.updateServerSizeIfNeeded();
};

/**
 * Helper function to setup the slice and window/level sliders
 */
midas.visualize.setupSliders = function () {
    $('#sliceSlider').slider({
        min: midas.visualize.left.bounds[4],
        max: midas.visualize.left.bounds[5],
        value: midas.visualize.left.midK,
        change: function(event, ui) {
            midas.visualize.changeSlice(ui.value);
        },
        slide: function(event, ui) {
            midas.visualize.updateSliceInfo(ui.value);
        }
    });
    $('#windowSlider').slider({
        range: true,
        min: midas.visualize.left.minVal,
        max: midas.visualize.left.maxVal,
        values: [midas.visualize.left.minVal, midas.visualize.left.maxVal],
        change: function(event, ui) {
            midas.visualize.changeWindow(ui.values);
        },
        slide: function(event, ui) {
            midas.visualize.updateWindowInfo(ui.values);
        }
    });
};

/**
 * Unregisters all mouse event handlers on the renderer
 */
midas.visualize.disableMouseInteraction = function (side) {
    var el = midas.visualize[side].renderer.view;
    el.onclick = null;
    el.onmousemove = null;
    el.onmousedown = null;
    el.onmouseup = null;
    el.oncontextmenu = null;
    el.ontouchstart = null;
    el.ontouchmove = null;
};

/**
 * Update the client GUI values for window and level, without
 * actually changing them in PVWeb
 */
midas.visualize.updateWindowInfo = function (values) {
    $('#windowInfo').html('Window: '+values[0]+' - '+values[1]);
};

/** Make the actual request to PVWeb to set the window */
midas.visualize.changeWindow = function (values) {
    paraview.left.plugins.midasdual.AsyncChangeWindow(function (retVal) {
        midas.visualize.left.lookupTable = retVal.lookupTable;
        paraview.left.sendEvent('Render', ''); //force a view refresh
    }, [values[0], 0.0, 0.0, 0.0, values[1], 1.0, 1.0, 1.0], json.visualize.colorArrayNames.left);
    paraview.right.plugins.midasdual.AsyncChangeWindow(function (retVal) {
        midas.visualize.right.lookupTable = retVal.lookupTable;
        paraview.right.sendEvent('Render', ''); //force a view refresh
    }, [values[0], 0.0, 0.0, 0.0, values[1], 1.0, 1.0, 1.0], json.visualize.colorArrayNames.right);
    midas.visualize.left.imageWindow = values;
    midas.visualize.right.imageWindow = values;
};

/** Change the slice and run appropriate slice filter on any meshes in the scene */
midas.visualize.changeSlice = function (slice) {
    slice = parseInt(slice);
    midas.visualize.currentSlice = slice;
    
    var params = {
        left: {
            volume: midas.visualize.left.input,
            slice: slice,
            sliceMode: midas.visualize.sliceMode
        },
        right: {
            volume: midas.visualize.right.input,
            slice: slice,
            sliceMode: midas.visualize.sliceMode
        }
    };

    paraview.left.plugins.midasdual.AsyncChangeSlice(function(retVal) {
        if(typeof midas.visualize.changeSliceCallback == 'function') {
            midas.visualize.changeSliceCallback(slice, 'left');
        }
        paraview.left.sendEvent('Render', ''); //force a view refresh
    }, params.left);
    paraview.right.plugins.midasdual.AsyncChangeSlice(function(retVal) {
        if(typeof midas.visualize.changeSliceCallback == 'function') {
            midas.visualize.changeSliceCallback(slice, 'right');
        }
        paraview.right.sendEvent('Render', ''); //force a view refresh
    }, params.right);
};

/**
 * Update the value of the current slice, without rendering the slice.
 */
midas.visualize.updateSliceInfo = function (slice) {
    var max;
    if(midas.visualize.sliceMode == 'XY Plane') {
        max = midas.visualize.left.bounds[5];
    }
    else if(midas.visualize.sliceMode == 'XZ Plane') {
        max = midas.visualize.left.bounds[3];
    }
    else { // YZ Plane
        max = midas.visualize.left.bounds[1];
    }
    $('#sliceInfo').html('Slice: ' + slice + ' of '+ max);
};

/**
 * Initialize or re-initialize the renderer within the DOM
 */
midas.visualize.switchRenderer = function (side) {
    if(midas.visualize[side].renderer == undefined) {
        midas.visualize[side].renderer = new JavaScriptRenderer(side+'JsRenderer', '/PWService');
        midas.visualize[side].renderer.init(paraview[side].sessionId, midas.visualize[side].activeView.__selfid__);
    }

    midas.visualize[side].renderer.bindToElementId(side+'Renderer');
    var el = $('#'+side+'Renderer');
    midas.visualize[side].renderer.setSize(el.width(), el.height());
    midas.visualize[side].renderer.start();
};

/**
 * Generate a list of fully saturated colors.
 * List will contain <size> color values that are RGB lists with each channel in [0, 1].
 */
midas.visualize._generateColorList = function (size) {
   var list = [];
   for(var i = 0; i < size; i++) {
      var hue = i*(1.0 / size);
      if(hue > 1.0) hue = 1.0;
      list.push(midas.visualize._hsvToRgb(hue, 1.0, 1.0));
   }
   return list;
};

/**
 * Helper function for converting HSV to RGB color space
 * HSV input values should be in [0, 1]
 * RGB output values will be in [0, 1]
 */
midas.visualize._hsvToRgb = function (h, s, v) {
    var r, g, b;

    var i = Math.floor(h * 6);
    var f = h * 6 - i;
    var p = v * (1 - s);
    var q = v * (1 - f * s);
    var t = v * (1 - (1 - f) * s);

    switch(i % 6) {
        case 0: r = v, g = t, b = p; break;
        case 1: r = q, g = v, b = p; break;
        case 2: r = p, g = v, b = t; break;
        case 3: r = p, g = q, b = v; break;
        case 4: r = t, g = p, b = v; break;
        case 5: r = v, g = p, b = q; break;
    }

    return [r, g, b];
};

/**
 * Set the mode to point selection within the image.
 */
midas.visualize.pointMapMode = function () {
    midas.createNotice('Click on the images to select points', 3500);

    // Bind click action on the render window
    $.each(['left', 'right'], function(i, side) {
        var el = $(midas.visualize[side].renderer.view);
        var bounds = midas.visualize[side].bounds; //alias the variable for shorthand
        el.unbind('click').click(function (e) {
            var x, y, z;
            if(midas.visualize.sliceMode == 'XY Plane') {
                var longLength = Math.max(bounds[1] - bounds[0], bounds[3] - bounds[2]);
                var arWidth = (bounds[1] - bounds[0]) / longLength;
                var arHeight = (bounds[3] - bounds[2]) / longLength;

                x = (bounds[1] - bounds[0]) * ((e.offsetX - ($(this).width() * (1-arWidth) / 2.0)) / ($(this).width() * arWidth));
                x -= bounds[0];
                
                y = (bounds[3] - bounds[2]) * ((e.offsetY - ($(this).height() * (1-arHeight) / 2.0)) / ($(this).height() * arHeight));
                y -= bounds[2];
                
                z = midas.visualize.currentSlice;
            }
            else if(midas.visualize.sliceMode == 'XZ Plane') {
                var longLength = Math.max(bounds[1] - bounds[0], bounds[5] - bounds[4]);
                var arWidth = (bounds[1] - bounds[0]) / longLength;
                var arHeight = (bounds[5] - bounds[4]) / longLength;

                x = (bounds[1] - bounds[0]) * ((e.offsetX - ($(this).width() * (1-arWidth) / 2.0)) / ($(this).width() * arWidth));
                x = bounds[1] - x;
                x -= midas.visualize.bounds[0];
                
                y = midas.visualize.currentSlice;
                
                z = (bounds[5] - bounds[4]) * ((e.offsetY - ($(this).height() * (1-arHeight) / 2.0)) / ($(this).height() * arHeight));
                z = bounds[5] - z;
                z -= bounds[4];
            }
            else if(midas.visualize.sliceMode == 'YZ Plane') {
                var longLength = Math.max(bounds[1] - bounds[0], bounds[5] - bounds[4]);
                var arWidth = (bounds[1] - bounds[0]) / longLength;
                var arHeight = (bounds[5] - bounds[4]) / longLength;

                x = midas.visualize.currentSlice;
                
                y = (bounds[3] - bounds[2]) * ((e.offsetX - ($(this).width() * (1-arWidth) / 2.0)) / ($(this).width() * arWidth));
                y -= bounds[2];
                
                z = (bounds[5] - bounds[4]) * ((e.offsetY - ($(this).height() * (1-arHeight) / 2.0)) / ($(this).height() * arHeight));
                z = bounds[5] - z;
                z -= bounds[4];
            }
            var surfaceColor = midas.visualize.pointColors[midas.visualize[side].points.length % midas.visualize.pointColors.length];
            var params = {
                point: [x, y, z],
                color: surfaceColor,
                radius: midas.visualize[side].maxDim / 85.0, //make the sphere some small fraction of the image size
                input: midas.visualize[side].input
            };
            paraview[side].plugins.midasdual.AsyncShowSphere(function (retVal) {
                midas.visualize[side].points.push({
                    object: retVal.glyph,
                    color: retVal.surfaceColor,
                    radius: retVal.radius,
                    x: x,
                    y: y,
                    z: z
                });
                paraview[side].sendEvent('Render', ''); //force a view refresh
            }, params);
        });
    });
};

/**
 * Set an action as active
 * @param button The button to display as active (all others will become inactive)
 * @param callback The function to call when this button is activated
 */
midas.visualize.setActiveAction = function (button, callback) {
    $('.actionActive').addClass('actionInactive').removeClass('actionActive');
    button.removeClass('actionInactive').addClass('actionActive');
    callback();
};

/**
 * Enable point selection action
 */
midas.visualize._enablePointMap = function () {
    var button = $('#actionButtonTemplate').clone();
    button.removeAttr('id');
    button.addClass('pointSelectButton');
    button.appendTo('#rightRendererOverlay');
    button.qtip({
        content: 'Select a single point in the image'
    });
    button.show();

    button.click(function () {
        midas.visualize.setActiveAction($(this), midas.visualize.pointMapMode);
    });

    var listButton = $('#actionButtonTemplate').clone();
    listButton.removeAttr('id');
    listButton.addClass('pointMapListButton');
    listButton.appendTo('#rightRendererOverlay');
    listButton.qtip({
        content: 'Show selected point map'
    });
    listButton.show();
    
    listButton.click(function () {
        alert('todo');
    });
};

/**
 * Enable the specified set of operations in the view
 * Options:
 *   -pointSelect: select a single point in the image
 */
midas.visualize.enableActions = function (side, operations) {
    if(side == 'right') {
        $.each(operations, function(k, operation) {
            if(operation == 'pointMap') {
                midas.visualize._enablePointMap();
            }
            else if(operation != '') {
                alert('Unsupported operation: '+operation);
            }
        });
    }
};

/**
 * Change the slice mode. Valid values are 'XY Plane', 'XZ Plane', 'YZ Plane'
 */
midas.visualize.setSliceMode = function (sliceMode) {
    if(midas.visualize.sliceMode == sliceMode) {
        return; //nothing to do, already in this mode
    }

    var slice, parallelScale, cameraPosition, min, max;
    if(sliceMode == 'XY Plane') {
        slice = Math.floor(midas.visualize.midK);
        parallelScale = Math.max(midas.visualize.bounds[1] - midas.visualize.bounds[0],
                                 midas.visualize.bounds[3] - midas.visualize.bounds[2]) / 2.0;
        cameraPosition = [midas.visualize.midI, midas.visualize.midJ, midas.visualize.bounds[4] - 10];
        cameraUp = [0.0, -1.0, 0.0];
        min = midas.visualize.bounds[4];
        max = midas.visualize.bounds[5];
    }
    else if(sliceMode == 'XZ Plane') {
        slice = Math.floor(midas.visualize.midJ);
        parallelScale = Math.max(midas.visualize.bounds[1] - midas.visualize.bounds[0],
                                 midas.visualize.bounds[5] - midas.visualize.bounds[4]) / 2.0;
        cameraPosition = [midas.visualize.midI, midas.visualize.bounds[3] + 10, midas.visualize.midK];
        cameraUp = [0.0, 0.0, 1.0];
        min = midas.visualize.bounds[2];
        max = midas.visualize.bounds[3];
    }
    else { // YZ Plane
        slice = Math.floor(midas.visualize.midI);
        parallelScale = Math.max(midas.visualize.bounds[3] - midas.visualize.bounds[2],
                                 midas.visualize.bounds[5] - midas.visualize.bounds[4]) / 2.0;
        cameraPosition = [midas.visualize.bounds[1] + 10, midas.visualize.midJ, midas.visualize.midK];
        cameraUp = [0.0, 0.0, 1.0];
        min = midas.visualize.bounds[0];
        max = midas.visualize.bounds[1];
    }
    midas.visualize.currentSlice = slice;
    midas.visualize.sliceMode = sliceMode;
    midas.visualize.updateSliceInfo(slice);
    $('#sliceSlider').slider('destroy').slider({
        min: min,
        max: max,
        value: slice,
        change: function(event, ui) {
            midas.visualize.changeSlice(ui.value);
        },
        slide: function(event, ui) {
            midas.visualize.updateSliceInfo(ui.value);
        }
    });
    
    var params = {
        volume: midas.visualize.input,
        slice: slice,
        sliceMode: sliceMode,
        meshes: midas.visualize.meshes,
        lineWidth: midas.visualize.maxDim / 100.0,
        parallelScale: parallelScale,
        cameraPosition: cameraPosition,
        cameraUp: cameraUp
    };
    paraview.plugins.midasslice.AsyncChangeSliceMode(function (retVal) {
        midas.visualize.meshSlices = retVal.meshSlices;
        paraview.sendEvent('Render', ''); //force a view refresh
    }, params);
};

$(window).load(function () {
    if(typeof midas.visualize.preInitCallback == 'function') {
        midas.visualize.preInitCallback();
    }

    json = jQuery.parseJSON($('div.jsonContent').html());
    midas.visualize.start();
});

$(window).unload(function () {
    paraview.left.disconnect();
    paraview.right.disconnect();
});
