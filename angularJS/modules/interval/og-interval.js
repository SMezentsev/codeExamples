/**
 * Created by s.mezencev on 18.08.17.
 */

(function(){
    'use strict';

    angular.module('app')
        .service('ngInterval',[
            '$interval','$state'

            ,function(
                $interval,$state
                ){
                var _interval = {};
                var state = '';



                return {

                    delete: function() {

                        return _delete();
                    },

                    deleteById: function(id) {

                        return _deleteById(id);
                    },
                    init: function(id,request,timer,firstRun) {


                        if(_interval[id] != undefined) {
                            $interval.cancel(id);
                        }
                        if (firstRun==undefined || firstRun)
                            request();

                        _interval[id] = $interval(function(){

                            if(state == '') {
                                state = $state.current.url;
                            } else {

//                            if(state != $state.current.url) {
//                                console.log('int_4')
//                                _delete();
//                                state = $state.current.url;
//                            } else {
//                                console.log('int_5')
//
//                            }

                                request();
                            }


                        },timer||1000);
                    },
                    status: function(id) {
                        return _interval[id] ? true : false;
                    },
                    getInterval: function() {
                        return _interval;
                    }
                };

                function _deleteById(id) {

                    if(id && typeof _interval[id] != 'undefined') {
                        $interval.cancel(_interval[id]);
                        og.del(_interval, id);
                    }

                }

                function _delete() {

                    $.each(_interval,function(i,id){
                        $interval.cancel(id);
                        //og.del(_interval, i)
                    });
                }


            }]);

})();