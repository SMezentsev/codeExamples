/**
 * Created by chilinduh on 22.07.15.
 */

(function(){
    'use strict';

    angular.module('app').
directive('map', function($leaflet,$baidu) {
    return {
        restrict: 'E',
        replace: true,
        priority: -1,
        template: '<div></div>',
        link: function(scope, element, attrs) {

            var defaultCenter = {
                lat: 55,
                lng: 55
            };
            var map = [];
            var options = scope.$eval(attrs.options)||{};
            var id = attrs.id;

            switch(id) {
                case 'osm': $leaflet.init(id,element[0],scope,{center: options.center||defaultCenter,zoom: options.zoom||5}); break;
                case 'baidu': scope.baidu = $baidu.init(id,element[0],scope,{center: options.center||defaultCenter,zoom: options.zoom||10}); break;
            }

//            if(tile == 'baidu') {
//
//                var map = new BMap.Map("baidu");
//                var point = new BMap.Point(116.404, 39.915);
//                map.centerAndZoom(point, 15);
//                map.enableScrollWheelZoom(true);
//                map.addControl(new BMap.NavigationControl());
//                map.addControl(new BMap.ScaleControl({anchor:BMAP_ANCHOR_TOP_LEFT}));
//
//            } else {
//                map[tile] = new L.Map(element[0], settings);
//                $leaflet.register(tile, map[tile], scope);
//            }

        }
    };
});


})();