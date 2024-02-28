
angular.module('app').
    service('$baidu',[
        '$timeout'
        ,function(
            $timeout
            ){

      attachBaidu();

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

                init: function(id, element, options) {

                  if (!window.BMap_loadScriptTime) {
                    (function(){ window.BMap_loadScriptTime = (new Date).getTime(); document.write('<script type="text/javascript" src="http://api.map.baidu.com/getscript?v=2.0&ak=wEdGBRYrr5t1tF9X9cGbSmkB&services=&t=20150807155150"></script>');})();
                  }


                  m[id] = {
                        map:  new BMap.Map(id)
                    };
                    var point = new BMap.Point(116.404, 39.915);
                    var points = new BMap.PointCollection([{lat:1,lng:1}]);

                    m[id].map.centerAndZoom(point, 15);
                    m[id].map.enableScrollWheelZoom(true);
                    m[id].map.addControl(new BMap.NavigationControl());
                    m[id].map.addControl(new BMap.ScaleControl({anchor:BMAP_ANCHOR_TOP_LEFT}))
                    _layerGroup[id] = [];
                    console.log('m[id].map',new BMap)
                    //ms[id] = scope;
                    return m[id];
                },
                getMap: function(id) {
                    var map = m[id];

                    return $.extend({map:map}, {

                        centerAndZoom: function(center,zoom) {
                            _centerAndZoom(id,center,zoom);
                        },
                        map: function() {
                            return m[id].map;
                        },
                        enableScrollWheelZoom: function(value) {
                            value = value||true;
                            _enableScrollWheelZoom(id,value);
                        },
                        createMarker: function(options) {
                            return _createMarker(options)
                        },
                        getBounds: function(points) {
                            if(points) {

                            } else {
                                return m[id].map.getBounds();
                            }

                        },
                        setCenter: function(options) {
                            options = options||{};
                            return m[id].map.setCenter({lat:options.lat,lng:options.lat});
                        },
                        getZoom: function() {
                            return m[id].map.getZoom();
                        },
                        addMarker: function(options) {
                            options = options||{};
                            var marker = _createMarker(options);
                            _addMarker(id,marker);
                        },
                        createLayerGroup: function(layerId,options) {
//                            _createLayerGroup(id,layerId,options)
                        },
                        drawPath: function(points,options) {

                            options = options||{strokeColor:"#000000", strokeWeight:1, strokeOpacity:0.5};
                            var path = [];
                            $.each(points, function(i,point){
                                path.push(new BMap.Point(point.lng,point.lat))
                            });
                            $timeout(function(){
                                m[id].map.addOverlay(new BMap.Polyline(path,options));
                            });
                        }

                    });
                }

            };

            function _getBounds() {


            }

            function _centerAndZoom(id,center,zoom) {
                center = center||{};
                zoom = zoom||{};

                m[id].map.centerAndZoom(center,zoom)
            }

            function _addMarker(id,marker) {

                m[id].map.addOverlay(marker);

                marker.addEventListener("click",function(){ //Click the event of marking
                    alert("You click on the label");
                });
            }

            function _createMarker(options) {
                return new BMap.Marker(options);
            }

            function _enableScrollWheelZoom(id,value) {
                m[id].map.enableScrollWheelZoom(value);
            }

        }]);

function attachBaidu() {
//  window.BMap_loadScriptTime = (new Date).getTime();
//  document.write('<script type="text/javascript" src="http://api.map.baidu.com/getscript?v=2.0&ak=wEdGBRYrr5t1tF9X9cGbSmkB&services=&t=20150807155150"></script>');
}