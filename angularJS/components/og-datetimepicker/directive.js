(function(){
    'use strict';


    angular.module('app')
        .directive('ogDatetimePicker',function($compile,$timeout,$rootScope,amDateFormatFilter,$parse){

            return {
                restrict: 'EA',
                terminal: true,
                link: function(scope,el,attr) {

                    var id = Math.round(Math.random()*1e10);
                    var display = $('<div class="input-group date dateTime-picker "   id="' + id + '">' +
                        '<span class="input-group-addon">' +
                          '<span class="fa fa-calendar form-control-feedback" style="cursor:pointer"></span>' +
                        '</span>' +
                        '</div>');

                    var input = $(el).find('input');

                    var options = scope.$eval($(el).attr('og-datetime-picker'));

                    var model = input.attr('ng-model');
                    var viewTime = (options && options['viewTime'])||false;
                    var format = (options && options['format'])||'YYYY-MM-DD';
                    var showClear = true;
                    var maxDate = (options && options['maxDate'])||false;
                    model = model.split(".");

                    if(typeof input.attr('clear') != 'undefined') {
                      showClear = scope.$eval(input.attr('clear'))  ? true : false;
                    }

                    display.prepend(input);
                    //display = $(el).prepend();
                    $(el).append(display);

//                    if(input.hasClass('readonly')) {
                        $(el).find('span').addClass('readonly');
//                    }

                    $timeout(function() {
                        var params = {
                            format : format,
                            locale: 'ru',
                            showClear: showClear,
                            showClose: true,
                            viewTime: viewTime,
                            ignoreReadonly: true,
                          // value:''
                             // maxDate: new Date(new Date().setDate(new Date().getDate() + 1)),
                            // focusOnShow: false,
                            // disabledDates: true
                        };

                        if(maxDate) {
                            $.extend(params,{maxDate:maxDate})
                        }
                        // if(options && !options['sideBySide']) {
                        //      $.extend(params,{sideBySide:true});
                        // } else {
                        // }

                        $('#' + id).datetimepicker(params
                        ).on('dp.change',function(e) {

                            scope[model[0]][model[1]] = display.find('input').val();

                            // var dateTime = display.find('input').val().split(' ');
                            // scope[model[0]][model[1]] = display.find('input').val(dateTime[0] + ' 00:00:00');

                        }).on('click', function(data) {


                            // scope[model[0]][model[1]] = display.find('input').val();

                          console.log('datadata',data)

                        });





                      $compile(display.find('input'))(scope);

                        $rootScope.$watch(function() {

                            return display.find('input').val();
                        },function(newH,oldH){
                        });

                    },100);

                }
            };
        });




})();
