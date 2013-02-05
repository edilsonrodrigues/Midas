var midas = midas || {};
midas.tracker = midas.tracker || {};

midas.tracker.OFFICIAL_COLOR_KEY = 'black';
midas.tracker.UNOFFICIAL_COLOR_KEY = 'red';

/**
 * In modern browsers that support window.history.replaceState,
 * this updates the currently displayed URL in the browser to make
 * permalinking easy.
 */
midas.tracker.updateUrlBar = function () {
    if(typeof window.history.replaceState == 'function') {
        var params = '?trendId='+json.tracker.trendIds;
        params += '&startDate='+$('#startdate').val();
        params += '&endDate='+$('#enddate').val();

        if(json.tracker.rightTrend) {
            params += '&rightTrendId='+json.tracker.rightTrend.trend_id;
            if(typeof json.tracker.y2Min != 'undefined' && typeof json.tracker.y2Max != 'undefined') {
                params += '&y2Min='+json.tracker.y2Min+'&y2Max='+json.tracker.y2Max;
            }
        }
        if(typeof json.tracker.yMin != 'undefined' && typeof json.tracker.yMax != 'undefined') {
            params += '&yMin='+json.tracker.yMin+'&yMax='+json.tracker.yMax;
        }
        window.history.replaceState({}, '', params);
    }
};

/**
 * Extract the jqplot curve data from the scalar daos passed to us
 */
midas.tracker.extractCurveData = function (curves) {
    var allPoints = [], allColors = [], minVal, maxVal;
    $.each(curves, function(idx, scalars) {
        if(!scalars) {
            return;
        }
        var points = [];
        var colors = [];
        $.each(scalars, function(idx, scalar) {
            var value = parseFloat(scalar.value);
            points.push([scalar.submit_time, value]);
            if(typeof minVal == 'undefined' || value < minVal) {
                minVal = value;
            }
            if(typeof maxVal == 'undefined' || value > maxVal) {
                maxVal = value;
            }
            if(scalar.official == 1) {
                colors.push(midas.tracker.OFFICIAL_COLOR_KEY);
            }
            else {
                colors.push(midas.tracker.UNOFFICIAL_COLOR_KEY);
            }
        });
        allPoints.push(points);
        allColors.push(colors);
    });
    return {
        points: allPoints,
        colors: allColors,
        minVal: minVal,
        maxVal: maxVal
    };
};

/**
 * Fill in the "info" sidebar section based on the curve data
 */
midas.tracker.populateInfo = function (curveData) {
    var count = curveData.points[0].length;
    if(json.tracker.rightTrend) {
        count += curveData.points[1].length;
    }
    $('#pointCount').html(count);
    $('#minVal').html(curveData.minVal);
    $('#maxVal').html(curveData.maxVal);
};

midas.tracker.bindPlotEvents = function () {
    $('#chartDiv').unbind('jqplotDataClick').bind('jqplotClick', function (ev, gridpos, datapos, dataPoint, plot) {
        if(dataPoint == null || typeof dataPoint.seriesIndex == 'undefined') {
            return;
        }
        var scalarId;
        if(!json.tracker.rightTrend || dataPoint.seriesIndex == 0) {
            scalarId = json.tracker.scalars[dataPoint.seriesIndex][dataPoint.pointIndex].scalar_id;
        } else {
            scalarId = json.tracker.rightScalars[dataPoint.pointIndex].scalar_id;
        }
        midas.loadDialog('scalarPoint'+scalarId, '/tracker/scalar/details?scalarId='+scalarId);
        midas.showDialog('Scalar details', false, {width: 500});
    });
};

midas.tracker.renderChartArea = function (curveData, first) {
    if(midas.tracker.plot) {
        midas.tracker.plot.destroy();
    }
    if(curveData.points[0].length > 0) {
        $('#chartDiv').html('');
        var opts = {
            axes: {
                xaxis: {
                    pad: 1.05,
                    renderer: $.jqplot.DateAxisRenderer,
                    tickRenderer: $.jqplot.CanvasAxisTickRenderer,
                    tickOptions: {
                        formatString: "%Y-%m-%d",
                        angle: 270,
                        fontSize: '11px',
                        labelPosition: 'middle'
                    }

                },
                yaxis: {
                    pad: 1.05,
                    label: midas.tracker.yaxisLabel,
                    labelRenderer: $.jqplot.CanvasAxisLabelRenderer,
                    labelOptions: {
                        angle: 270,
                        fontSize: '12px'
                    }
                }
            },
            highlighter: {
                show: true,
                showTooltip: true
            },
            cursor: {
                show: true,
                zoom: true,
                showTooltip: false
            },
            series: []
        };
        // Now assign official/unofficial color to each marker
        $.each(curveData.colors, function(idx, trendColors) {
            opts.series[idx] = {
                renderer: $.jqplot.DifferentColorMarkerLineRenderer,
                rendererOptions: {
                    markerColors: curveData.colors[idx],
                    shapeRenderer: $.jqplot.ShapeRenderer,
                    shadowRenderer: $.jqplot.ShadowRenderer
                }
            };
        });
        if(json.tracker.rightTrend) {
            opts.legend = {
                show: true,
                labels: [json.tracker.trends[0].display_name, json.tracker.rightTrend.display_name],
                location: 'se'
            };
            opts.axes.y2axis = {
                show: true,
                pad: 1.05,
                label: midas.tracker.yaxis2Label,
                labelRenderer: $.jqplot.CanvasAxisLabelRenderer,
                labelOptions: {
                    angle: 270,
                    fontSize: '12px'
                },
                showLabel: true
            };
            opts.series[0].yaxis = 'yaxis';
            opts.series[1].yaxis = 'y2axis';

            if(typeof json.tracker.y2Min != 'undefined' && typeof json.tracker.y2Max != 'undefined') {
                opts.axes.y2axis.min = parseFloat(json.tracker.y2Min);
                opts.axes.y2axis.max = parseFloat(json.tracker.y2Max);
                opts.axes.y2axis.pad = 1.0;
            }
        }
        else if(json.tracker.trends.length > 1) {
            var labels = [];
            $.each(json.tracker.trends, function(key, trend) {
                var label = trend.display_name;
                if(trend.unit != '') {
                    label+=' ('+trend.unit+')';
                }
                labels.push(label);
            });
            opts.legend = {
                show: true,
                location: 'se',
                labels: labels
            };
        }

        if(typeof json.tracker.yMin != 'undefined' && typeof json.tracker.yMax != 'undefined') {
            opts.axes.yaxis.min = parseFloat(json.tracker.yMin);
            opts.axes.yaxis.max = parseFloat(json.tracker.yMax);
            opts.axes.yaxis.pad = 1.0;
        }

        midas.tracker.plot = $.jqplot('chartDiv', curveData.points, opts);
        midas.tracker.bindPlotEvents();

        $('a.resetZoomAction').unbind('click').click(function () {
            midas.tracker.plot.resetZoom();
        });
    }
    else {
        $('#chartDiv').html('<span class="noPoints">There are no values for this trend in the specified date range.</span>');
    }
    if(first) {
        $.jqplot.postDrawHooks.push(midas.tracker.bindPlotEvents); //must re-bind data click each time we redraw
    }
    midas.tracker.populateInfo(curveData);
};

$(window).load(function () {
    var inputCurves = json.tracker.scalars;
    if(json.tracker.rightTrend) {
        inputCurves.push(json.tracker.rightScalars);
    }
    var curveData = midas.tracker.extractCurveData(inputCurves);

    if(json.tracker.trends.length == 1) {
        midas.tracker.yaxisLabel = json.tracker.trends[0].display_name;
        if(json.tracker.trends[0].unit) {
            midas.tracker.yaxisLabel += ' ('+json.tracker.trends[0].unit+')';
        }
    }
    else {
        midas.tracker.yaxisLabel = '';
    }
    if(json.tracker.rightTrend) {
        midas.tracker.yaxis2Label = json.tracker.rightTrend.display_name;
        if(json.tracker.rightTrend.unit) {
            midas.tracker.yaxis2Label += ' ('+json.tracker.rightTrend.unit+')';
        }
    }

    var dates = $("#startdate, #enddate").datepicker({
        defaultDate: "today",
        changeMonth: true,
        numberOfMonths: 1,
        onSelect: function(selectedDate) {
          var option = this.id == "startdate" ? "minDate" : "maxDate";
          var instance = $(this).data("datepicker");
          var date = $.datepicker.parseDate(
            instance.settings.dateFormat || $.datepicker._defaults.dateFormat,
            selectedDate, instance.settings);
          dates.not(this).datepicker("option", option, date);
          },
        dayNamesMin: ["S", "M", "T", "W", "T", "F", "S"]
    });
    $('#startdate').val(json.tracker.initialStartDate);
    $('#enddate').val(json.tracker.initialEndDate);
    $('#filterButton').click(function () {
        $(this).attr('disabled', 'disabled');
        $('#dateRangeUpdating').show();
        var params = {
            trendId: json.tracker.trendIds,
            startDate: $('#startdate').val(),
            endDate: $('#enddate').val()
        };
        if(json.tracker.rightTrend) {
          params.rightTrendId = json.tracker.rightTrend.trend_id;
        }
        $.post(json.global.webroot+'/tracker/trend/scalars', params, function (retVal) {
            var resp = $.parseJSON(retVal);
            json.tracker.scalars = resp.scalars;
            json.tracker.rightScalars = resp.rightScalars;
            var inputCurves = json.tracker.scalars;
            if(json.tracker.rightTrend) {
                inputCurves.push(json.tracker.rightScalars);
            }
            midas.tracker.updateUrlBar();
            midas.tracker.renderChartArea(midas.tracker.extractCurveData(inputCurves), false);
            $('#filterButton').removeAttr('disabled');
            $('#dateRangeUpdating').hide();
        });
    });

    midas.tracker.renderChartArea(curveData, true);

    $('a.thresholdAction').click(function () {
        midas.loadDialog('thresholdNotification', '/tracker/trend/notify?trendId='+json.tracker.trends[0].trend_id);
        midas.showDialog('Email notification settings', false);
    });
    $('a.axesControl').click(function () {
        midas.showDialogWithContent('Axes Controls', $('#axesControlTemplate').html(), false, {width: 380});
        var container = $('div.MainDialog');
        container.find('input.yMin').val(json.tracker.yMin);
        container.find('input.yMax').val(json.tracker.yMax);
        container.find('input.y2Min').val(json.tracker.y2Min);
        container.find('input.y2Max').val(json.tracker.y2Max);
        container.find('input.updateAxes').unbind('click').click(function () {
            json.tracker.yMin = container.find('input.yMin').val();
            json.tracker.yMax = container.find('input.yMax').val();
            if(json.tracker.rightTrend) {
                json.tracker.y2Min = container.find('input.y2Min').val();
                json.tracker.y2Max = container.find('input.y2Max').val();
            }
            midas.tracker.renderChartArea(curveData, false);
            midas.tracker.updateUrlBar();
            container.dialog('close');
        });
    });
    $('a.deleteTrend').click(function () {
        midas.showDialogWithContent('Confirm Delete Trend', $('#deleteTrendTemplate').html(), false, {width: 420});
        var container = $('div.MainDialog');
        container.find('input.deleteYes').unbind('click').click(function () {
            $(this).attr('disabled', 'disabled');
            container.find('input.deleteNo').attr('disabled', 'disabled');

            midas.ajaxWithProgress(container.find('div.deleteProgressBar'),
                container.find('div.deleteProgressMessage'),
                json.global.webroot+'/tracker/trend/delete',
                {trendId: json.tracker.trendIds},
                midas.tracker.trendDeleted
            );
        });
        container.find('input.deleteNo').unbind('click').click(function () {
            $('div.MainDialog').dialog('close');
        });
    });
});

midas.tracker.trendDeleted = function (resp) {
    window.location = json.global.webroot+'/tracker/producer/view?producerId='+json.tracker.producerId;
};