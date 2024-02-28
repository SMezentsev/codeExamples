(function(){
  'use strict';

    angular.module('app').
        directive('onlyPattern', function () {

            return {
                restrict: 'A',
                require: '?ngModel',
                link: function (scope, element, attrs, modelCtrl) {

                    var pattern = false;
                    if(attrs.pattern){
                        pattern = '/' + attrs.onlyPattern + '/';
                    }


                    modelCtrl.$parsers.push(function (inputValue) {
                        if (inputValue == undefined) return '';
//                        var transformedInput = inputValue.replace(/[^0-9]/g, '');
                        var transformedInput = inputValue.replace(/\s+/g, '');
                        if(!pattern) {
                            transformedInput = inputValue.replace(/[^0-9A-Za-z!#.?<>\"\'%&*\)\(+=.,:;/_-]/, '');
                        } else {
                            console.log('sdfsdfsd',pattern)
                            transformedInput = inputValue.replace(pattern, '');
                        }

                        if (transformedInput !== inputValue) {
                            modelCtrl.$setViewValue(transformedInput);
                            modelCtrl.$render();
                        }
                        return transformedInput;
                    });
                }
            };
        });

    angular.module('app').
        directive('translit', function ($timeout) {

            return {
                restrict: 'A',
                require: '?ngModel',
                link: function (scope, element, attrs, modelCtrl) {

                    var pattern = '/' + attrs.pattern + '/';

                    var customMap = {
                        'х': 'X', 'Х' : 'X', 'у': 'Y' , 'У': 'Y'  , 'к': 'K' , 'К':'K'  , 'е' : 'E' , 'Е' :'E' ,
                        'а': 'A' , 'А': 'A', 'о': 'O' ,  'О': 'O', 'с' :'C'  , 'С': 'C' , 'н':'H', 'Н':'H',
                        'р' :'P' , 'Р' :'P'  ,  'м': 'M' ,'М': 'M' ,  'т' :'T', 'Т' :'T', 'в':'B','В':'B'
                    };

                    var mapU = {
                        'Й': 'Q', 'Ц' : 'W', 'У': 'E' , 'К': 'R'  , 'Е': 'T' , 'Н':'Y'  , 'Г' : 'U' , 'Ш' :'I' ,
                        'Щ': 'O' , 'З': 'P',
                        'Ф': 'A' ,  'Ы': 'S', 'В' :'D'  , 'А': 'F' , 'П': 'G' ,'Р'  : 'H' , 'О': 'J' , 'Л': 'K', 'Д' : 'L' ,
                        'Я' :'Z' , 'Ч' :'X'  , 'С':'C' ,  'М': 'V' ,'И': 'B' ,  'Т' :'N', 'Ь' :'M'  ,
                    };

                    var mapL = {
                        'й': 'Q', 'ц' : 'W', 'у': 'E' , 'к': 'R'  , 'е': 'T' , 'н':'Y'  , 'г' : 'U' , 'ш' :'I' ,
                        'щ': 'O' , 'з': 'P',
                        'ф': 'A' ,  'ы': 'S', 'в' :'D'  , 'а': 'F' , 'п': 'G' ,'р'  : 'H' , 'о': 'J' , 'л': 'K', 'д' : 'L' ,
                        'я' :'Z' , 'ч' :'X'  , 'с':'C' ,  'м': 'V' ,'и': 'B' ,  'т' :'N', 'ь' :'M'  ,
                    };


//                    mapU = Object.assign(mapU, customMap);
//                    mapL = Object.assign(mapL, customMap);
                    mapU = customMap;
                    mapL = customMap;
                    function getCaretPosition(ctrl) {
                        if (document.selection) {
                            ctrl.focus();
                            var range = document.selection.createRange();
                            var rangelen = range.text.length;
                            range.moveStart('character', -ctrl.value.length);
                            var start = range.text.length - rangelen;
                            return {
                                'start': start,
                                'end': start + rangelen
                            };
                        } else if (ctrl.selectionStart || ctrl.selectionStart == '0') {
                            return {
                                'start': ctrl.selectionStart,
                                'end': ctrl.selectionEnd
                            };
                        } else {
                            return {
                                'start': 0,
                                'end': 0
                            };
                        }
                    }


                    function setCaretPosition(ctrl, start, end) {
                        ctrl.focus();
                        ctrl.setSelectionRange(start, end);
                        if (ctrl.setSelectionRange) {

                        } else if (ctrl.createTextRange) {
//                            var range = ctrl.createTextRange();
//
//                            range.collapse(true);
//                            range.moveEnd('character', end);
//                            range.moveStart('character', start);
//                            range.select();
                        }
                    };
                    var outpz = '';


                    modelCtrl.$parsers.push(function (inputValue) {

                        outpz = getCaretPosition(element[0]);

                        var r = '';
                        if (inputValue == undefined) return '';

                        for (var i = 0; i <= inputValue.length + 1; i++) {

                            if(mapU[inputValue.charAt(i)]) {
                                r += mapU[inputValue.charAt(i)]
                            } else if(mapL[inputValue.charAt(i)]) {
                                r += mapL[inputValue.charAt(i)]
                            } else {
                                r += inputValue.charAt(i);
                            }

                        }
//                        var transformedInput = inputValue.replace(/[^0-9]/g, '');
                        //var transformedInput = inputValue.replace(/\s+/g, '');
                        //transformedInput = inputValue.replace(/[^0-9A-Za-z!#.?<>\"\'%&*\)\(+=.,:;/_-]/, '');

                        //if (r !== inputValue) {
                            modelCtrl.$setViewValue(r);
                            modelCtrl.$render();
                        console.log('5464654654',outpz.start, outpz.end)
                            $timeout(function(){
                                setCaretPosition(element[0], outpz.start, outpz.end);
                            })
                        //}

                        return r;
                    });
                }
            };
        });


  angular.module('app')
    .directive('ogNegative',function($timeout,$compile) {
          return {
              restrict: 'A',
              require: 'ngModel',
              link: function(scope, elem, attrs, ctrl) {
                  scope.$watch(function() {
                      return elem.val();
                  },function(val, oldVal) {
                      if(val < 0) {
                          elem.val(val*(-1))
                      }
                  });
              }
          };
      })
    .directive('ogValidate',function($timeout,$compile) {
      var map = {
        //'required=required':'required',
        'type=number':'number',
        'type=text':'text',
        'type=email':'email',
        'ng-minlength':'minlength',
        'ng-maxlength':'maxlength',
        'pattern': 'pattern',
        'ng-pattern': 'pattern',
        'og-backend': 'backend',
        'equal-to': 'equalTo',
        'typeahead-editable=false': 'editable'
      };

      return {
        restrict: 'A',
        require:'?^form',
        priority: -1,
        compile: function(el,attr){

          var name = el.attr('name');
          var type = el.attr('type');
          var translateCategory = el.closest('[translate-category]').attr('translate-category');

          var formModel = attr.ngModel.replace(/\./,'Form.');

          var messages = $('<div ng-messages="'+formModel+'.$error" class="text-danger" role="alert"></div>');
          var backendMessage;

            angular.forEach(map,function(validationKey,validationAttr) {

            validationAttr = validationAttr.split('=');
            if (
              (validationAttr[1]!==undefined && el.attr(validationAttr[0])==validationAttr[1])
                ||
              (validationAttr[1]===undefined && el.attr(validationAttr[0])!==undefined)
            ) {

              if (validationKey=='backend') {
                backendMessage = true;
              } else {
                messages.append('<div ng-message="'+validationKey+'"><span translate="'+translateCategory+'.invalid-'+validationKey+'-'+name+'"></span></div>');
              }
              var message = validationKey=='backend'?'<span>{{'+formModel+'.$error.backend}}</span>':'';
            }
          });

          if (backendMessage) {
            messages.append($('<div ng-show="!'+formModel+'.$invalid && '+formModel+'.$backendError"><span>{{'+formModel+'.$backendError}}</span>&nbsp;&nbsp;&nbsp;<b class="og-backend-validate-dismiss" ng-click="'+formModel+'.$backendError=null">&times;</b></div>'));
          }

          el.closest('[field]').append(messages);

          return {
            pre: function(scope){
              $compile(messages)(scope);
            },
            post: function(scope,el,attr,form){

              if (form) {
                form.$setBackendErrors = form.$setBackendErrors||function(data,fieldsMap,messageParse){

                  var th = this;
                  messageParse = messageParse||function(text){
                    return text.replace(/Error: (.*)/,'$1');
                  };
                  fieldsMap = fieldsMap||{};
                  angular.forEach(data,function(error){
                    if (error.field && error.message) {
                      var field = fieldsMap[error.field] ? fieldsMap[error.field] : error.field;
                      if (th[field]!==undefined) {
                        th[field].$backendError = messageParse(error.message);
                      }
                    }
                  });
                };

              }

              $timeout(function(){
                var label = el.closest('[field]').find('>label');
                if (label.length) {
                  var model = el.controller('ngModel');

                  if (model) {
                    /// required
                    scope.$watch(function(){
                      return model.$error.required;
                    },function(error){
                      if (error) {
                        label.append('<b class="text-danger"> *</b>');
                      } else {
                        label.find('b').remove();
                      }
                    });

                  }
                }

              },250);

            }
          };
        }
      };
    });
})();

