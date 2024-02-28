/**
 * Created by chilinduh on 29.06.15.
 */
(function(){
    'use strict';

    angular.module('app')

    .directive('video', [function () {
        return {
            restrict: 'A',
            link: function (scope, element, attrs) {


                var firstRun = true;
                attrs.$observe('src', function(value) {
                    value = value||'';

                    if(value != '') {
                        attrs.type = attrs.type || "video/mp4";
                        attrs.id = attrs.id || "video" + Math.floor(Math.random() * 100);
                        attrs.setup = attrs.setup || {};
                        attrs.height = attrs.height || {};
                        var setup = {
                            'techOrder': ['html5', 'flash'],
                            'controls': true,
                            'preload': 'auto',
                            'width': "100%",
                            'height': "100%",
                            'poster': ''
                        };

                        if(!firstRun){
                            element.attr('autoplay',true);
                        }

                        setup = angular.extend(setup, attrs.setup);
                        element.attr('id', attrs.id);

                        var player = videojs(attrs.id, setup, function() {
                            this.src({
                                type: attrs.type,
                                src: value
                            });
                        });

                        firstRun = false;
                    }
                });
            }
        };
    }]);

})();