/**
 * Created by chilinduh on 30.07.15.
 */


(function(){
    'use strict';


    angular.module('app').
        service('$leaflet',[
            '$timeout'
            ,function(
                $timeout
                ){

          L.Icon.Default.imagePath = 'images';

                var m = {},
                    ms = {},
                    _center = {},
                    _markers = {},
                    _layerId = null,
                    _layerGroup = [],
                    _defaultMarkerOptions = {
                        focus:true,
                        opacity:1,
                        popupOptions: {
                            closeButton: false,
                            closeOnClick: false,
                            autoPan: false
                        }
                    },
                    _defaultLayerOptions = {
                        layers: {
                            baselayers: {
                                osm: {
                                    name: 'OpenStreetMap (XYZ)',
                                    url: 'http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                                    type: 'xyz'
                                }
                            }
                            /*                  overlays: {
                             'name': {
                             type:'group',
                             visible:false,
                             name: 'layer'
                             }
                             }*/
                        }
                    },
                    _defaultOverLayerOptions = {
                        type:'group',
                        visible:false,
                        name: 'layer'
                    },
                    _defaultCircleOptions = {
                        color: 'red',
                        fillColor: 'red',
                        fillOpacity: 1
                    },
                    _defaultPathOptions = {
                        color: '#008000',
                        weight: 4,
                        type: 'polyline',
                        id: ''
                    };

                return {

                    init: function(id, element, scope, options) {

                        $.extend(options,{'layers':[_getTile()]});

                        m[id] = {
                            map:  new L.Map(element, options),
                            scope: scope
                        };
                        //_layerGroup[id] = {};
                        //ms[id] = scope;
//                        _initLayer(m[id].map);
                    },

                    getMap: function(id) {
                        var map = m[id];

                        return $.extend({map:map}, {

                            map: function() {
                                return m[id].map;
                            },
                            getZoom: function() {
                                return m[id].map.getZoom()
                            },
                            getActivePath: function() {
                                return ms[id].paths['path'] ? ms[id].paths['path'].latlngs : false;
                            },
                            getActiveMarkerID: function() {
                                return ms[id].activeMarkerID - 1;
                            },
                            getActivePathID: function() {
                                return ms[id].activePathID ? ms[id].activePathID : false
                            },
                            showMarkers: function(data) {
                                _removeNotActiveMarker(ms[id],data);
                                return _showMarkers(ms[id], data)
                            },
                            removeAllMarkers: function(data) {
                                return _removeAllMarkers(ms[id], data)
                            },
                            removePath: function(path_id) {
                                return _removePath(ms[id], path_id)
                            },
                            clearPath: function(path_id) {
                                return _clearPath(ms[id], path_id)
                            },
                            removeMarker: function(marker_id) {
                              console.log(map);
                                return _removeMarker(map, marker_id)
                            },
                            createLayerGroup: function(layerId) {
                                return _createLayerGroup(id,layerId);
                            },
                            pushInLayerGroup: function(layerId,point,options) {
                                return _pushInLayerGroup(id,layerId,point,options)
                            },
                            removeLayerGroup: function(layerId) {
                                _removeLayerGroup(id,m[id].map, layerId);
                            },
                            setLayerStyle: function(layerId,options) {
                                _setLayerStyle(id,layerId,options);
                            },
                            getLayerGroup: function(layerId) {
                                return _getLayerGroup(id,layerId);
                            },
                            getLayerGroupBounds: function(layerId) {
                                return _getLayerGroupBounds(id,layerId);
                            },
                            copyPolyLineInGroupLayer: function(fromLayerId,toLayerId,options){
                                return _copyPolyLineInGroupLayer(id,m[id].map,fromLayerId,toLayerId,options);
                            },
                            addPolyLineLatLng: function(layerId, data, options) {
                                _addPolyLineLatLng(layerId, data, options);
                            },
                            drawPolyLineInGroupLayer: function(layerId, data, options) {
                                return _drawPolyLineInGroupLayer(id, m[id].map, layerId, data, options)
                            },
                            drawAllPolyLineInGroupLayer: function(options) {
                                return _drawAllPolyLineInGroupLayer(id,m[id].map, options)
                            },
                            findMarker: function(findBy) {
                                return _findMarker(ms[id], findBy)
                            },
                            fitMapByLatLng: function(data) {
                                return _fitMapByLatLng(ms[id], data)
                            },
                            drawPath: function(data, options) {
                                return _drawPath(m[id].map, ms[id], data, options)
                            },
                            drawPoint: function(data, options) {
                                return _drawPoint(m[id].map, ms[id], data, options)
                            },
                            drawCircle: function(data, options) {
                                return _drawCircle(m[id].map, ms[id], data, options)
                            },
                            drawPathById: function(path_id, data, options) {
                                return _drawPathById(path_id, ms[id], data, options)
                            },
                            addButton: function(data) {
                                return _addLink(ms[id], data)
                            },
                            addControl: function(type, data, options) {
                                return _addControl(m[id].map, type, data, options)
                            },
                            createMarker: function(data) {
                                return _createMarker(data)
                            },
                            addMarker: function(data) {
                                return _addMarker(m[id], data)
                            },
                            updateMarkerPosition: function(markerId, data, options) {
                                return _updateMarker(ms[id], markerId, data, options)
                            },
                            createLayer: function(data) {
                                return _createLayer(m[id].map, data)
                            },
                            initLayers: function(data) {
                                return _initLayer(ms[id])
                            },
                            centerMapByLatLng: function(data) {
                                return _centerMapByLatLng(m[id].map,data)
                            },
                            getDistance: function (lat1, lng1, lat2, lng2) {
                                return _getDistance(lat1, lng1, lat2, lng2);
                            },
                            getRandomColor: function() {
                                return _getRandomColor();
                            }

                        })
                    }
                };


                function _getTile(tile,options) {
                    options = options||{};
                    return L.tileLayer(_defaultLayerOptions.layers.baselayers.osm.url,{id: tile, attribution: options})
                }

                function _initLayer(map) {

                    //L.control.layers(_defaultLayerOptions.layers).addTo(map);
                    var options = $.extend({},_defaultLayerOptions, {default:_defaultOverLayerOptions});
                    L.control.layers(_defaultLayerOptions.layers).addTo(map);
                    map.addLayer(_defaultLayerOptions.layers);
                }

                function _setLayerStyle(id,layerId, options) {
                    options = options||{};
                    var layerGroup = _getLayerGroup(id,layerId);
                    //layerGroup.instance.setStyle(options);

                    for(var i=0;i<layerGroup.latLngs.length;i++) {
                        layerGroup.latLngs[i].setStyle(options);
                    }
                }

                function _createLayerGroup(id,layerId,options) {
                  _layerGroup[id] = _layerGroup[id]||{};
                  _layerGroup[id][layerId] = [];
                    _layerGroup[id][layerId] = $.extend({
                        latLngs:[],
                        instance:L.layerGroup([])
                    },options);
                }

                function _pushInLayerGroup(id,layerId, data, options) {
                    var index = 0;
                    var popupOptions = {};
                    if(data) {
                      //console.log('data',data);
                        index = _layerGroup[id][layerId].latLngs.push(L.polyline([[data.startPoint.lat,data.startPoint.lng],[data.endPoint.lat,data.endPoint.lng]], options));
                        //console.log(index);
                        if(typeof options.popup !== 'undefined') {
                            popupOptions = options.popup.options;
                            _layerGroup[id][layerId].latLngs[index-1].bindPopup(options.popup.text,popupOptions);
                        }
                    }
                }

                function _drawAllPolyLineInGroupLayer(id,map,settings) {
                    if(_layerGroup[id]){
                        //console.log('_layerGroup[id]',_layerGroup[id])
                        $.each(_layerGroup[id],function(layerId,layer){
                            _drawPolyLineInGroupLayer(id,map,layerId,layer.latLngs);
                        });
                    }
                }


                function _getRandomColor() {
                    var letters = '0123456789ABCDEF'.split('');
                    var color = '#';
                    for (var i = 0; i < 6; i++ ) {
                        color += letters[Math.floor(Math.random() * 16)];
                    }
                    return color;
                }

                function _drawPolyLineInGroupLayer(id, map, layerId, data, settings) {

                    if(typeof _layerGroup[id][layerId] != 'undefined') {
                        _layerGroup[id][layerId].instance = L.layerGroup(data);
                        $timeout(function(){
//                            console.log(_layerGroup[id][layerId].instance,data)
                            _layerGroup[id][layerId].instance.addTo(map);
                            return _layerGroup[id][layerId];
                        });
                    } else {
                        console.log('Layer instance not found')
                    }
                }

                function _copyPolyLineInGroupLayer(id, map, fromLayerId, toLayerId, settings) {
                    var polyLine = _getLayerGroup(fromLayerId);
                    var tempPath = [];
                    var data = {};
                    settings = settings||{};
                    if(typeof _layerGroup[id][toLayerId] == 'undefined') {
                        _createLayerGroup(toLayerId);
                    }

                    if(_layerGroup[id][fromLayerId]) {
                        $.each(_layerGroup[id][fromLayerId].latLngs, function(i,line) {
                            data = line.getLatLngs();
                            _pushInLayerGroup(id,toLayerId, {
                                startPoint:{lat:data[0].lat,lng:data[0].lng},
                                endPoint:{lat:data[1].lat,lng:data[1].lng}
                            }, $.extend(line.options,settings));
                        });
                        $timeout(function(){
                            tempPath = _getLayerGroup(toLayerId);
                            _drawPolyLineInGroupLayer(id, map, toLayerId, tempPath.latLngs)
                        });
                    }
                    return _layerGroup[id][toLayerId];
                }

                function _getLayerGroup(id,layerId) {
                    if(typeof layerId != 'undefined') {
                        return _layerGroup[id][layerId];
                    } else {
                        return _layerGroup[id];
                    }

                }

                function _getLayerGroupBounds(id,layerId) {
                    return _layerGroup[id][layerId].instance.getBounds();
                }

                function _removeLayerGroup(id,map, layerId) {
                    if(_layerGroup[id] && _layerGroup[id][layerId]) {
                        map.removeLayer(_layerGroup[id][layerId].instance);
                        $timeout(function(){
                            delete(_layerGroup[id][layerId]);
                        })
                    }
                }

                function _clearPath(scope,id) {
                    if(scope.paths[id]) {
                        //scope.paths[id].latlngs.splice(0,scope.paths[id].latlngs.length);
                        scope.paths[id].latlngs = [];
                        $timeout(function(){
                        });
                    }
                }

                function _removePath(scope) {
                    var activePathID = scope.activePathID;
                    if(scope.paths[activePathID]) {
                        delete scope.paths[activePathID];
                        delete scope.activePathID;
                    }
                    scope.paths = {};
                }

//                function _removeMarker(scope, id) {
//                    id = id||false;
//                    var activeMarkerID = scope.activeMarkerID - 1;
//
//                    if(id) {
//                        var marker = $.findInBy(scope.markers,'id',id);
//
//                        if(marker) {
//                            $.delInBy(scope.markers, 'id', id);
//                        }
//                    } else if(scope.markers[activeMarkerID]) {
//                        delete scope.markers[activeMarkerID];
//                    }
//                }

                function _removeNotActiveMarker(scope, markers) {

                    var activeMarkers = angular.copy(scope.markers);
                    $.each(activeMarkers, function(i,marker){
                        var active = $.findInBy(markers,'id',marker.id);
                        if(!active) {
                            $.delInBy(scope.markers, 'id', marker.id);
                        }
                    });
                }

                function _centerMapByLatLng(map, data, options) {

                    options = options||{padding: [25, 25]};
                    var points = new L.LatLngBounds;

                    if(data) {
                        $.each(data, function(i,r) {
                            if(r.lat  && r.lng) {
                                points.extend(r);
                            }
                        });

                        $timeout(function() {
                            if(points.isValid()) {
                                map.fitBounds(points, options);
                            }
                        });
                    }
                }

                function _findMarker(scope, findBy, id) {

                    var marker = $.findInBy(scope.markers, findBy, id);
                    return marker ? marker: false;
                }

                function _setLayerOptions(data) {

                    data = data||{};
                    var options = {};

                    if(data.name) {
                        options[data.name] = data.name;
                        options[data.name] = $.extend({},_defaultOverLayerOptions, options);
                        return {name:data.name,layer:options};
                    } else {
                        console.log('Layer name empty');
                        return false
                    }
                }

                function _getActiveLayerId(scope) {
                    return scope.layers.activeLayerId||false;
                }

                function _setMarkerOptions(data) {

                    data = data||{};
                    var options = {};

                    if(data.lat && data.lng) {

                        options.lat = data.lat;
                        options.lng = data.lng;
                        options.id = data.id;
                        options.active = data.active||false;
                        options.message = data.message||'Marker';
                        if(data.popupOptions) {
                            options.popupOptions = data.popupOptions;
                        }

                        return $.extend({},_defaultMarkerOptions, options);

                    } else {
                        return false
                    }
                }


                function _move(scope,data,i) {

                    if(!$scope.play) {
                        return false;
                    }
                    i = i||0;
                    var delay = 0;
                    _updateMarker(scope.activeMarkerID, [data[i]], {});

                    if(data[i+1]) {
                        delay = scope.getDelayBetweenPoints(data[i], data[i+1]);
                    }

                    delay = (delay*1000)/scope.states.speed;
                    $timeout(function(){
                        scope.move(map,data,i);
                    }, delay);
                    i++;

                }

                function _createMarker(data, layer) {

                    return _setMarkerOptions(data, layer);
                }

                function _addMarker(map, data, layerOptions) {
                    layerOptions = layerOptions||_defaultOverLayerOptions;
                    var layer = {};
                    var marker;

//                    var activeLayerId = _getActiveLayerId(scope);
//                    if(!scope.activeLayerID) {
//                        layer = _createLayer(scope, layerOptions);
//                        $.extend(scope, {activeLayerID:layerOptions.name});
//                    }

                    var options = _createMarker(data, layer);

                  marker = L.marker([options.lat,options.lng]).addTo(map.map);

                    //$timeout(function() {
                        //marker = $.findInBy(map.markers, 'id', options.id);
                        if(typeof marker != 'undefined') {
                            $.extend(marker,options)
                        } else {
                            //marker = L.marker.add(options);
                            //marker = L.marker([options.lat,options.lng]).addTo(map.map);
//                            if(options.active) {
//                                $.extend(map, {activeMarkerID:marker});
//                            }
                        }
                    //});
                    return marker;

                }

                function _removeMarker(map,marker) {
                  //console.log(map);
                  map.map.removeLayer(marker);
                }

                function _updateMarker(scope, markerID, data, options) {

                    data = data||{};

                    if(data && scope.markers[markerID]) {
                        $.extend(scope.markers[markerID], data);
                    }
                }

                function _removeAllMarkers(scope) {

                    scope.markers = [];
                }

                function _addControl(map, type, data) {

                    data = data||{};
                    if(data) {
                        $.extend(data, {btnFunction: _actions(type)});
                        $timeout(function(){
                            L.Buttons(map, data.buttonOptions);
                        });
                    }
                }

                function _createLayer(scope, data) {

                    var options = {};
                    options = _setLayerOptions(data);
                    $.extend(scope.layers.overlays, options.layer);
                    return options.name;
                }

                function _actions(type, scope, data, options) {

                    if(type) {

                        switch(type) {
                            case 'path': return _drawPath(scope, options);
                            case 'marker': return _addMarker(scope, options);
                            default: return function(){}
                        }
                    }
                }

                function _addLink() {

                    var myButton = L.control({ position: 'bottomleft' });

                    myButton.onAdd = function () {
                        this._div = L.DomUtil.create('div', 'myButton-css-class');
                        this._div.innerHTML = '<a href="#">Return to Drupal</a>';
                        return this._div;
                    };
                    myButton.addTo(_map);
                }

                function _drawPathById(path_id, scope, data, settings) {
                    $.each(data,function(i,item){
                        scope.paths[path_id].latlngs.push(item);
                    });
                }

                function _drawPoint(map, scope, data, settings) {
                    var points = [];
                    map.panBy(data);
                }

                function _drawCircle(map, scope, data, settings) {

                    var options = settings||{};
                    options = $.extend({},_defaultCircleOptions, settings);
                    var circle =  L.circle(data, options.radius, options).addTo(map);

                }

                function _updatePolyLine() {

                }

                function _addPolyLineLatLng(id,layerId, data, options) {
                    var polyline = L.polyline([[data.startPoint.lat,data.startPoint.lng],[data.endPoint.lat,data.endPoint.lng]], options);
                    _layerGroup[id][layerId].instance.addLatLng(polyline);
                }

                function _drawPolyLine(map, scope, data, settings) {
                    var options = settings||{};
                    var polyline = new L.Polyline(data, options);
                    polyline.addTo(map);
                }

                function _drawPath(map, scope, data, settings) {

                    settings = settings||{};

                    var points = [];
                    var options = _defaultPathOptions;
                    var path = {};
                    var pathId = 'path';
                    var paths = [];
                    var currentPoint = {};

                    if(settings.id != '') {
                        pathId = settings.id;
                    }

                    $.each(data, function(i,r) {
                        points.push({
                                lat: r.lat,
                                lng: r.lng,
                                type: 'polyline',
                                speed: r.speed,
                                time: r.datetime
                            }
                        );
                    });

                    $timeout(function() {
                        options = $.extend({},_defaultPathOptions, settings);
                        paths[pathId] = $.extend({},options,{latlngs:points});
                        $.extend(scope.paths,paths);

                        if(typeof settings.active != 'undefined') {
                            $.extend(scope, {activePathID:pathId});
                        }
                        _centerMapByLatLng(map, points);
                    });
                }

                function _showMarkers(scope, data) {
                    var markers = [];

                    $.each(data, function(i,r) {
                        _addMarker(scope, r);
                    });

                }

                function _fitMapByLatLng(scope, data) {
                    $timeout(function() {
                        scope.center = data;
                    });
                }

                function _getDistance(lat1, lng1, lat2, lng2) {
                    //радиус Земли
                    var R = 6372795;

                    //перевод коордитат в радианы
                    lat1 *= Math.PI / 180;
                    lat2 *= Math.PI / 180;
                    lng1 *= Math.PI / 180;
                    lng2 *= Math.PI / 180;

                    //вычисление косинусов и синусов широт и разницы долгот
                    var cl1 = Math.cos(lat1);
                    var cl2 = Math.cos(lat2);
                    var sl1 = Math.sin(lat1);
                    var sl2 = Math.sin(lat2);
                    var delta = lng2 - lng1;
                    var cdelta = Math.cos(delta);
                    var sdelta = Math.sin(delta);

                    //вычисления длины большого круга
                    var y = Math.sqrt(Math.pow(cl2 * sdelta, 2) + Math.pow(cl1 * sl2 - sl1 * cl2 * cdelta, 2));
                    var x = sl1 * sl2 + cl1 * cl2 * cdelta;
                    var ad = Math.atan2(y, x);
                    var dist = ad * R; //расстояние между двумя координатами в метрах

                    return dist
                }

            }]);


})();









