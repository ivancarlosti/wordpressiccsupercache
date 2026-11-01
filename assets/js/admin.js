( function ( $, root, undefined ) {
    root.icc_gg_wordpress_super_cache = root.icc_gg_wordpress_super_cache || {};
    var icc_gg_wordpress_super_cache = root.icc_gg_wordpress_super_cache;

    $.extend( icc_gg_wordpress_super_cache, {
        metrics: {
            computed: null,
        },
        chart: null,
        chart_defaults: {
            noData: {
                text: root.icc_gg_wordpress_super_cache_metrics
                    ? icc_gg_wordpress_super_cache.l10n.no_data
                    : icc_gg_wordpress_super_cache.l10n.no_cache,
                align: 'center',
                verticalAlign: 'middle',
                offsetY: -25,
                style: {
                    color: '#72777c',
                    fontSize: '14px',
                    fontFamily: 'inherit',
                }
            },
            stroke: {
                width: [2, 2],
                curve: 'smooth',
                dashArray: [0, 8],
            },
            colors: [
                icc_gg_wordpress_super_cache.is_wp7 ? '#3858e9' : '#0096dd',
                '#72777c',
            ],
            annotations: {
                texts: [{
                    x: '15%',
                    y: '30%',
                    fontSize: '20px',
                    fontWeight: 600,
                    fontFamily: 'inherit',
                    foreColor: '#72777c',
                }],
            },
            chart: {
                type: 'line',
                height: $( '#redis-pane #widget-redis-stats' ).length ? '300px' : '100%',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: false },
            },
            dataLabels: {
                enabled: false,
            },
            legend: {
                show: false,
            },
            fill: {
                opacity: [0.25, 1],
            },
            xaxis: {
                type: 'datetime',
                labels: {
                    format: 'HH:mm',
                    datetimeUTC: false,
                    style: { colors: '#72777c', fontSize: '13px', fontFamily: 'inherit' },
                },
                tooltip: { enabled: false },
            },
            yaxis: {
                type: 'numeric',
                tickAmount: 4,
                min: 0,
                labels: {
                    style: { colors: '#72777c', fontSize: '13px', fontFamily: 'inherit' },
                    formatter: function ( value ) {
                        return Math.round( value );
                    },
                },
            },
            tooltip: {
                fixed: {
                    enabled: true,
                    position: 'bottomLeft',
                    offsetY: 15,
                    offsetX: 0,
                },
            }
        },
        templates: {
            tooltip_title: _.template(
                '<div class="apexcharts-tooltip-title"><%- title %></div>'
            ),
            series_group: _.template(
                '<div class="apexcharts-tooltip-series-group">' +
                '  <span class="apexcharts-tooltip-marker" style="background-color: <%- color %>;"></span>' +
                '  <div class="apexcharts-tooltip-text">' +
                '    <div class="apexcharts-tooltip-y-group">' +
                '      <span class="apexcharts-tooltip-text-label"><%- name %>:</span>' +
                '      <span class="apexcharts-tooltip-text-value"><%- value %></span>' +
                '    </div>' +
                '  </div>' +
                '</div>'
            ),
        }
    } );

    // Build the charts by deep extending the chart defaults
    $.extend( icc_gg_wordpress_super_cache, {
        charts: {
            time: $.extend( true, {}, icc_gg_wordpress_super_cache.chart_defaults, {
                yaxis: {
                    labels: {
                        formatter: function ( value ) {
                            return Math.round( value ) + ' ms';
                        },
                    },
                },
                tooltip: {
                    custom: function ({ series, seriesIndex, dataPointIndex, w }) {
                        return [
                            icc_gg_wordpress_super_cache.templates.tooltip_title({
                                title: new Date( w.globals.seriesX[ seriesIndex ][ dataPointIndex ] )
                                    .toTimeString().slice( 0, 5 ),
                            }),
                            icc_gg_wordpress_super_cache.templates.series_group({
                                color: icc_gg_wordpress_super_cache.chart_defaults.colors[0],
                                name: w.globals.seriesNames[0],
                                value: series[0][ dataPointIndex ].toFixed(2) + ' ms',
                            }),
                        ].join('');
                    },
                },
            } ),
            bytes: $.extend( true, {}, icc_gg_wordpress_super_cache.chart_defaults, {
                yaxis: {
                    labels: {
                        formatter: function ( value ) {
                            var i = value === 0 ? 0 : Math.floor( Math.log( value ) / Math.log( 1024 ) );

                            return parseFloat( (value / Math.pow( 1024, i ) ).toFixed( i ? 2 : 0 ) ) + ' ' + ['B', 'KB', 'MB', 'GB', 'TB'][i];
                        },
                    },
                },
                tooltip: {
                    custom: function ({ series, seriesIndex, dataPointIndex, w }) {
                        var value = series[0][ dataPointIndex ];
                        var i = value === 0 ? 0 : Math.floor( Math.log( value ) / Math.log( 1024 ) );
                        var bytes = parseFloat( (value / Math.pow( 1024, i ) ).toFixed( i ? 2 : 0 ) ) + ' ' + ['B', 'KB', 'MB', 'GB', 'TB'][i];

                        return [
                            icc_gg_wordpress_super_cache.templates.tooltip_title({
                                title: new Date( w.globals.seriesX[ seriesIndex ][ dataPointIndex ] ).toTimeString().slice( 0, 5 ),
                            }),
                            icc_gg_wordpress_super_cache.templates.series_group({
                                color: icc_gg_wordpress_super_cache.chart_defaults.colors[0],
                                name: w.globals.seriesNames[0],
                                value: bytes,
                            }),
                        ].join('');
                    },
                },
            } ),
            ratio: $.extend( true, {}, icc_gg_wordpress_super_cache.chart_defaults, {
                yaxis: {
                    max: 100,
                    labels: {
                        formatter: function ( value ) {
                            return Math.round( value ) + '%';
                        },
                    },
                },
                tooltip: {
                    custom: function ({ series, seriesIndex, dataPointIndex, w }) {
                        return [
                            icc_gg_wordpress_super_cache.templates.tooltip_title({
                                title: new Date( w.globals.seriesX[ seriesIndex ][ dataPointIndex ] )
                                    .toTimeString().slice( 0, 5 ),
                            }),
                            icc_gg_wordpress_super_cache.templates.series_group({
                                color: icc_gg_wordpress_super_cache.chart_defaults.colors[0],
                                name: w.globals.seriesNames[0],
                                value: Math.round( series[0][ dataPointIndex ] * 100 ) / 100 + '%',
                            }),
                        ].join('');
                    },
                },
            } ),
            calls: $.extend( true, {}, icc_gg_wordpress_super_cache.chart_defaults, {
                tooltip: {
                    custom: function ({ series, seriesIndex, dataPointIndex, w }) {
                        return [
                            icc_gg_wordpress_super_cache.templates.tooltip_title({
                                title: new Date( w.globals.seriesX[ seriesIndex ][ dataPointIndex ] )
                                    .toTimeString().slice( 0, 5 ),
                            }),
                            icc_gg_wordpress_super_cache.templates.series_group({
                                color: icc_gg_wordpress_super_cache.chart_defaults.colors[0],
                                name: w.globals.seriesNames[0],
                                value: Math.round( series[0][ dataPointIndex ] ),
                            }),
                        ].join('');
                    },
                },
            } ),
        },
    } );

    var compute_metrics = function ( raw_metrics ) {
        var metrics = {};

        // parse raw metrics in blocks of minutes
        for ( var entry in raw_metrics ) {
            var values = {};
            var timestamp = raw_metrics[ entry ].timestamp;
            var minute = ( timestamp - timestamp % 60 ) * 1000;

            for ( var key in raw_metrics[ entry ] ) {
                if ( raw_metrics[ entry ].hasOwnProperty( key ) ) {
                    values[ key ] = Number( raw_metrics[ entry ][ key ] );
                }
            }

            if ( ! metrics[ minute ] ) {
                metrics[ minute ] = [];
            }

            metrics[ minute ].push( values );
        }

        // calculate median value for each block
        for ( var entry in metrics ) {
            if ( metrics[ entry ].length === 1 ) {
                metrics[ entry ] = metrics[ entry ].shift();
                continue;
            }

            var medians = {};

            for ( var key in metrics[ entry ][0] ) {
                medians[ key ] = compute_median(
                    metrics[ entry ].map(
                        function ( metric ) {
                            return metric[ key ];
                        }
                    )
                );
            }

            metrics[ entry ] = medians;
        }

        var computed = [];

        for ( var timestamp in metrics ) {
            var entry = metrics[ timestamp ];

            entry.date = Number( timestamp );
            entry.time = entry.time * 1000;

            computed.push( entry );
        }

        computed.sort(
            function( a, b ) {
                return a.date - b.date;
            }
        );

        return computed.length < 2 ? [] : computed;
    };

    var compute_median = function ( numbers ) {
        var median = 0;
        var numsLen = numbers.length;

        numbers.sort();

        if ( numsLen % 2 === 0 ) {
            median = ( numbers[ numsLen / 2 - 1 ] + numbers[ numsLen / 2 ] ) / 2;
        } else {
            median = numbers[ ( numsLen - 1 ) / 2 ];
        }

        return median;
    };

    var render_chart = function ( id ) {
        if ( icc_gg_wordpress_super_cache.chart ) {
            icc_gg_wordpress_super_cache.chart.updateOptions( icc_gg_wordpress_super_cache.charts[ id ] );
            return;
        }

        var chart = new ApexCharts(
            document.querySelector( '#redis-stats-chart' ),
            icc_gg_wordpress_super_cache.charts[ id ]
        );

        chart.render();
        root.icc_gg_wordpress_super_cache.chart = chart;
    };

    var setup_charts = function () {
        var metrics = {};

        for ( var type in icc_gg_wordpress_super_cache.charts ) {
            if ( ! icc_gg_wordpress_super_cache.charts.hasOwnProperty( type ) ) {
                continue;
            }

            metrics[type] = icc_gg_wordpress_super_cache.metrics.computed.map(
                function ( entry ) {
                    return [ entry.date, entry[type] ];
                }
            );

            icc_gg_wordpress_super_cache.charts[type].series = [{
                name: icc_gg_wordpress_super_cache.l10n[type],
                type: 'area',
                data: metrics[type],
            }];
        }
    };

    // executed on page load
    $(function () {
        var $tabs = $( '#icc-gg-wordpress-super-cache .nav-tab-wrapper' );
        var $panes = $( '#icc-gg-wordpress-super-cache .content-column .tab-content' );

        $tabs.find( 'a' ).on(
            'click.icc-gg-wordpress-super-cache',
            function ( event ) {
                var toggle = $( this ).data( 'toggle' );

                $( this ).blur();

                show_tab( toggle );

                if ( history.pushState ) {
                    history.pushState( null, null, '#' + toggle );
                }

                return false;
            }
        );

        var firstRender = window.location.hash.indexOf('redis') === -1;

        var show_tab = function ( name ) {
            $tabs.find( '.nav-tab-active' ).removeClass( 'nav-tab-active' );
            $panes.find( '.tab-pane.active' ).removeClass( 'active' );

            $( '#' + name + '-tab' ).addClass( 'nav-tab-active' );
            $( '#' + name + '-pane' ).addClass( 'active' );

            if (name === 'redis' && firstRender) {
                firstRender = false;
                render_chart( 'time' );
            }
        };

        var show_current_tab = function () {
            var tabHash = window.location.hash.replace( '#', '' );

            if ( tabHash !== '' && $( '#' + tabHash + '-tab' ) ) {
                show_tab( tabHash );
            }
        };

        show_current_tab();

        $( window ).on( 'hashchange', show_current_tab );

        if ( $( '#widget-redis-stats' ).length ) {
            icc_gg_wordpress_super_cache.metrics.computed = compute_metrics( root.icc_gg_wordpress_super_cache_metrics );

            setup_charts();
            render_chart( 'time' );
        }

        $( '#widget-redis-stats ul a[data-chart]' ).on(
            'click.icc-gg-wordpress-super-cache',
            function ( event ) {
                event.preventDefault();

                $( '#widget-redis-stats .active' ).removeClass( 'active' );
                $( this ).blur().addClass( 'active' );

                render_chart(
                    $( event.target ).data( 'chart' )
                );
            }
        );

        if ( $( '#icc-gg-wordpress-super-cache-copy-button' ).length ) {
            if ( typeof ClipboardJS === 'undefined' ) {
                $( '#icc-gg-wordpress-super-cache-copy-button' ).remove();
            } else {
                var successTimeout;
                var clipboard = new ClipboardJS( '#icc-gg-wordpress-super-cache-copy-button .copy-button' );

                clipboard.on( 'success', function( e ) {
                    var triggerElement = $( e.trigger ),
                        successElement = $( '.success', triggerElement.closest( 'div' ) );

                    e.clearSelection();
                    triggerElement.trigger( 'focus' );

                    clearTimeout( successTimeout );
                    successElement.removeClass( 'hidden' );

                    successTimeout = setTimeout( function() {
                        successElement.addClass( 'hidden' );

                        if ( clipboard.clipboardAction.fakeElem && clipboard.clipboardAction.removeFake ) {
                            clipboard.clipboardAction.removeFake();
                        }
                    }, 3000 );

                } );
            }
        }
    });

} ( window[ icc_gg_wordpress_super_cache.jQuery ], window ) );
