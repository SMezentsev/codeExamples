(function(){
    'use strict';

    angular.module('app')

        .directive('ogDatepicker',function($compile,$timeout,amDateFormatFilter,$rootScope,$parse){
            return {
                restrict: 'A',
                terminal: true,
                //priority: -1,

                compile: function(el,attr,ngModelCtrl){

                    console.log('1',ngModelCtrl);
                    var options = $parse(el.attr('og-datepicker'))()||{};

                    options.format = options.format||'ll';

                    el.removeAttr('og-datepicker');
                    el.attr('datepicker-popup','');

                    var dpOptions;
                    angular.forEach(['minMode','maxMode'],function(key){
                        if (options[key]) {
                            dpOptions = dpOptions||{};
                            dpOptions[key] = options[key];
                        }
                    });

                    if (dpOptions) {
                        el.attr('datepicker-options',angular.toJson(dpOptions));
                    }

                    el.attr('clear-text');
                    angular.forEach({
                        mode: 'datepicker-mode'
                    },function(attr,key){
                        if (options[key]) {
                            el.attr(attr,'\''+options[key]+'\'');
                        }
                    });
                    console.log('1')
                    el.attr('show-weeks',el.attr('show-weeks')||false);
                    el.attr('clear',true);
                    el.attr('show-button-bar',el.attr('show-button-bar')||false);
                    el.attr('type','text');

                    el.parent().addClass('has-feedback og-datepicker-input');
                    el.after('<i class="fa fa-calendar form-control-feedback"></i>');
                   // el.after('<i class="fa fa-close form-control-feedback"></i>');
                    //el.attr('ng-click','$root.dp'+id+'=!$root.dp'+id);

                    var display = $('<div><div class="form-control display"></div></div>').css('position','relative');

//          var display = $('<div ng-class="{open:dp'+id+'}"><div class="form-control display"></div></div>').css('position','relative');
                    el.before(display);
                    display.append(el);

                    return {

                        pre: function(scope,el,attr,ngModel){
                            $timeout(function(){
                                $compile(el)(scope);
                            });
                        },
                        post: function(scope,el,attr,ngModel){
                            $timeout(function() {

                                var depend = el.attr('depend')||false;
                                var clear = el.attr('clear')||false;
                                var input = el.find('[datepicker-popup]').addClass('empty');
                                var display = el.find('.display');
                                var dropdownMenu = el.find('.dropdown-menu');
                                var buttons = $(dropdownMenu);

                                var clearButton = $('<li class="datepicker-clear"></li>').append($('<a>').attr({ 'data-action': 'close'}).append($('<span>').addClass('glyphicon glyphicon-trash')));

                              $(clearButton).click(function() {
                                // ngModelCtrl.$setViewValue = 'sdfsdfs';

                                scope.$eval(attr.ngModel);

                                scope.$apply(function(){
                                  el.find('input').val('');
                                  el.find('.form-control.display').html('');
                                  attr.ngModel = ''
                                  scope[attr.ngModel] = '';
                                });
                                console.log('attr.ngModel111',scope.$eval(attr.ngModel));

                              });

                                // buttons.append(clearButton);

                                input.focus(function(e){
                                    e.preventDefault();
                                    input.blur();
                                });

                                display.parent().click(function(){
                                    input.data('open',!input.data('open'));
                                    scope.$digest();
                                });
                                scope.$watch(function(){
                                    return $('#' + $(el).find('table').attr('aria-labelledby')).html();
                                },function(open){
                                    if (open) {
                                        unset(depend);
                                    } else {
                                        display.parent().removeClass('open');
                                    }
                                });

                                scope.$watch(function(){
                                    return input.data('open');
                                },function(open){
                                    if (open) {
                                        unset(depend);
                                        display.parent().addClass('open');
                                    } else {
                                        display.parent().removeClass('open');
                                    }

                                });

                                var unwatch = scope.$watch(function(){
                                    return input.val();
                                },function(val){
                                    var date = '';

                                    if (val) {

                                        if(attr.clear) {

                                            var from = moment($(el).val()).format('YYYY-MM');
                                            var to = moment($('#' + attr.cleartarget).val()).format('YYYY-MM');
                                            console.log(from,to,attr)
                                            if(from > to) {

                                                var index = scope.$eval(attr.clear);

                                                if(index.length == 1) {
                                                    scope[index[0]] = '';
                                                }
                                                if(index.length == 2) {
                                                    scope[index[0]][index[1]] = '';
                                                }

                                            }

                                        }

                                        date = amDateFormatFilter(new Date(val),options.format);
                                        input.removeClass('empty');

                                    } else {
                                        input.addClass('empty');
                                    }
                                    $timeout(function(){
                                        display.text(date);
                                    });
                                });

                                scope.$on('$destroy',function(){
                                    unwatch();
                                });

                                function unset(depend) {
                                    var dependInputVal = $('#' + depend).val();
                                    if(dependInputVal) {
                                        var tds = el.find('td');

                                        $.each(tds, function(i,td) {

                                            var time = $(td).attr('id').split('-');

                                            var inputMonth = Number(time[3]);
                                            var inputYear = Number($('#' + time[0] + '-' + time[1] + '-' + time[2] + '-title').find('strong').html());

                                            var dependMonth = Number(moment(dependInputVal).format('MM'));
                                            var dependYear = Number(moment(dependInputVal).format('YYYY'));
                                            inputMonth =  inputMonth + 1;
                                            if(depend.indexOf('from') + 1) {

                                                if(inputYear > dependYear) {
                                                    var button = $(td).find('button').html();
                                                    //$(td).html(button);
                                                    //$(td).html(button).addClass('btn-disabled');
                                                    $(td).find('button').attr('disabled', true)
                                                }  else {
                                                    if(inputYear == dependYear && dependMonth < inputMonth  ) {
                                                        var button = $(td).find('button').html();
                                                        //$(td).html(button);
                                                        //  $(td).html(button).addClass('btn-disabled');
                                                        $(td).find('button').attr('disabled', false)
                                                    } else {
                                                        $(td).find('button').attr('disabled', true)
                                                    }
                                                }
                                            } else {
                                                if(inputYear < dependYear) {
                                                    var button = $(td).find('button').html();
                                                    //$(td).html(button);
                                                    //$(td).html(button).addClass('btn-disabled');
                                                    $(td).find('button').attr('disabled', true)
                                                }  else {
                                                    if(inputYear == dependYear && dependMonth > inputMonth) {
                                                        var button = $(td).find('button');//.html();
                                                        //$(td).html(button);
                                                        //$(td).find('button').addClass('btn-disabled');
                                                        //$(td).find('button').attr('disabled', true)
                                                        $(td).find('button').attr('disabled', true)

                                                    } else {

                                                        var button = $(td).find('button').html();
                                                        $(td).find('button').attr('disabled', false)
                                                        //$(td).html(button);
//                                                  $(td).html(button).removeClass('btn-disabled');

                                                    }
                                                }
                                            }
                                        })
                                    }
                                }
                            });
                        }
                    };
                }
            };
        });

})();
