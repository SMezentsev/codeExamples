(function(){
    'use strict';

    angular.module('app')
        .directive('field',formFieldDirective)
        .directive('formErrors',formErrorsDirective)
        .directive('a',disabledDirective)

        .directive('replaceComma', function(){
            return {
                restrict: 'A',
                replace: true,
                link: function(scope, element, attrs) {

                    scope.$watch(attrs.ngModel, function (v) {

                        if(attrs.ngModel == ',') {
                            attrs.NgModel = String(v).replace(",","");
                        }
                        if(attrs.ngModel == '.') {
                            attrs.NgModel = String(v).replace(".","");
                        }

                    });
                }
            }
        })

        .directive('submitProgress', function($compile) {
            return {
                restrict: 'A',
                replace: true,
                link: function(scope, el, attrs) {
                    var loader = $compile('<span ng-show="$root.submitProgress" loader="{width:\'15px\'}"></span>')(scope);
                    console.log('dsfsdfds',$(el).find('button[type="submit"]'))

                    $.each($(el).find('button[type="submit"]'), function(key,item){

                        $(item).append(loader);
                    })
                }
            }
        });

    function disabledDirective() {

        return {
            restrict:'E',
            priority:1,
            link: function(scope,el,attr) {

                if(attr.ngDisabled) {

                    scope.$watch(function(){
                        return attr.disabled;
                    }, function(){
                        if(attr.disabled) {

                            $(el).addClass('disabled')
                        } else {
                            $(el).removeClass('disabled')
                        }
                    })
                }

            }
        }

    }

    function formSubmitDirective($compile,$q) {
        return {
            restrict:'A',
            priority:5000,
            link: function(scope,el,attr) {
                //var loader = $('<i class="btn-loader icon fa fa-spinner fa-spin"></i>');
                var loader = $('<img ng-src="ajax-loader.gif" alt="" src="ajax-loader.gif" style="width: 25px; height: 25px;">');

                $(el).click(function() {
                    el.after(loader);
                    $(this).hide();
                })

                scope.$on('submitFinish',function() {
                    el.show();
                    el.next('img').hide();
                });
            }
        }
    };


    function formErrorsDirective($compile) {
        return {
            restrict:'A',
            priority:5000,
            link: function(scope,el,attr) {

                var errors = '<div ng-show="errors.length" class="alert alert-danger" role="alert" ng-repeat="error in errors">' +
                    '<i class="fa fa-exclamation-circle"></i> ' +
                    '    {{error.message}}' +
                    '</div>';

                var options = scope.$eval(attr.formErrors);

                errors = $compile(errors)(scope);

                if(options && typeof options.errors != 'undefined' && options.errors == 'append') {

                    $(el).find('div:last').append(errors);

                } else {
                    $(el).find('div:last').prepend(errors);
                }

                scope.$watch(function(errors){
                        return attr.errors;
                    },
                    function(errors){

                    });

            }
        }
    };

    function formFieldDirective($compile,$timeout) {
        return {

            restrict:'A',
            priority:5000,
            link: function(scope,el,attr){

                el.addClass('form-group');

                var input = el.find('[ng-model]:not([checkbox])').addClass('form-control');
                var translateCategory = el.closest('[translate-category]').attr('translate-category');

                if (attr.label!=undefined) {

                    var label = attr.label||input.attr('name');
                    var labelClass = '';
                    var fieldlClass = '';

                    if(el.closest('.form-horizontal').length) {

                        labelClass = 'class="col-sm-4 control-label"';
                        //fieldlClass = 'class="col-sm-8"';

                        //var html = el.html();

                        //el.html('<div ' + fieldlClass + '>' + html + '</div>');
                    }

                    label = '<label ' + labelClass + ' ' + (attr.label ? 'style="margin-left:15px"' : '') + ' translate="'+translateCategory+'.label-'+label+'"></label>';
                    if (el.closest('.form-inline').length) {
                        label += '<br>';
                    }

                    label = $compile(label)(scope);
                    el.prepend(label);

                    $timeout(function(){
                        var model = input.controller('ngModel');
                        if (model) {
                            /// has value
                            scope.$watch(function(){
                                return model.$modelValue;
                            },function(val){

                                if (val==undefined || val=='' || val == 0) {
                                    el.addClass('input-is-empty').removeClass('input-has-value');
                                } else {
                                    el.removeClass('input-is-empty').addClass('input-has-value');
                                }
                            });
                        }
                    });

                    var inputType = input.attr('type');
                    if (inputType=='password1') {
                        el.addClass('has-feedback');
                        var showHide = $('<i class="icon fa fa-eye-slash form-control-feedback show-hide-password" ' +
                            '></i>');
                        el.append(showHide);
                        showHide.on('click',function(){
                            input.attr('type',input.attr('type')=='password'?'text':'password');
                            showHide.toggleClass('fa-eye fa-eye-slash');
                        });
                    }

                    el.find('[open-eye-by-click]').click(function(){
                        input.attr('type','text');
                        showHide.removeClass('fa-eye-slash');
                        showHide.addClass('fa-eye');
                    });

                }

            }
        }
    }


    function _copyNgClasses(input,field) {
        field.removeClass(field.attr('class').split(' ').map(function(item){
            return item.match(/^ng-/) ? item : '';
        }).join(' '));
        $timeout(function(){
            field.addClass(input.attr('class').split(' ').map(function(item){
                return item.match(/^ng-/) ? item : '';
            }).join(' '));
        });
    }

})();
