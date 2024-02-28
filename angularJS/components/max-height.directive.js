(function(){
  'use strict';

  angular.module('app')
    .directive('maxHeight',maxHeightDirective)
      .directive('widthLike',function($window,$timeout,$rootScope,$compile) {

          return {
              restrict: 'A',
              link: function(scope,el,attrs) {

                  var options = scope.$eval(attrs.widthLike);
                  var reduceDynamic = scope.$eval(attrs.reducedynamic);

                  function getReduce() {
                      return options.reduce;
                  }

                  if(reduceDynamic) {
                      scope.$watch(function(){
                          return reduceDynamic();
                      },function(){
                          options.reduce = reduceDynamic();

                          var width = $(options.element).width();

                          if(typeof options.reduceElement != 'undefined') {
                              width = width - $(options.reduceElement).width();

                          } else if(typeof options.reduce != 'undefined') {

                              width = width - options.reduce;
                          }

                          $timeout(function(){
                              $(el).width(width);
                          },50);
                      });

                  }

                  function clientWidth() {
                      return document.documentElement.clientWidth;
                  }

                  scope.$watch(function() {
                      return  clientWidth();
                  }, function() {

                      var width = $(options.element).width();

                      if(typeof options.reduce != 'undefined') {
                          width = width - options.reduce;
                      }

                      $timeout(function(){
                          $(el).width(width);
                      },50);

                  })

              }
          }

      });

  function maxHeightDirective($window,$timeout,$rootScope) {
    return {
      restrict: 'A',
      link: function(scope,el,attrs){
        var style = {
          height: '100%',
          'max-height': '100%'
        };

              var options = scope.$eval(attrs.maxHeight);

          if(typeof options.reduce !== 'undefined') {
              var w = angular.element($window);
              scope.getWindowDimensions = function () {
                  return {
                      'h': w.height(),
                      'w': w.width()
                  };
              };

              if(options.reduce.type === 'px') {
                 var clientHeight = $(document).height();
                  clientHeight = clientHeight - options.reduce.value;

                  $.extend(style,{height:clientHeight+options.reduce.type,'max-height':clientHeight+options.reduce.type});
              }

              if(options.type !== 'auto') {
                  var wi = angular.element($window);
                  scope.getWindowDimensions = function () {
                      return {
                          'h': wi.height(),
                          'w': wi.width()
                      };
                  };

                  var blockright = document.getElementById("block-right");
                  var calendar = document.getElementsByClassName("calendar-filter")[0];
                  var hCalendar = (calendar) ? calendar.offsetHeight : 0;
                  var padsBlockright = (blockright) ? (blockright.clientTop) : 0;
                  var offset = padsBlockright + hCalendar + 140; // 140 - высота top-panel (120) плюс отступ снизу (20

                  var clHeight = $(document).height();
                  clHeight = clHeight - offset;

                  $.extend(style,{height:clHeight+'px','max-height':clHeight+'px'});

                  scope.$watch(scope.getWindowDimensions, function (newValue, oldValue) {

                      scope.windowHeight = newValue.h;
                      scope.windowWidth = newValue.w;

                      blockright = document.getElementById("block-right");
                      calendar = document.getElementsByClassName("calendar-filter")[0];
                      hCalendar = (calendar) ? calendar.offsetHeight : 0;
                      padsBlockright = (blockright) ? (blockright.clientTop) : 0;
                      offset = padsBlockright + hCalendar + 140;

                      var height = newValue.h;
                      height = height - offset;

                      $.extend(style,{height:height+'px','max-height':height+'px'});
                      el.css(style);
//                      el.parent().css(style);

                  }, true);
              }

              if(typeof options.type  !== 'undefined' && options.type === 'auto') {

                  scope.$watch(scope.getWindowDimensions, function (newValue, oldValue) {

                      scope.windowHeight = newValue.h;
                      scope.windowWidth = newValue.w;

                      var height = newValue.h;
                      height = height - options.reduce.value;

                      $.extend(style,{height:height+options.reduce.type,'max-height':height+options.reduce.type});
                      el.css(style);
//                      el.parent().css(style);

                  }, true);
              }
              else {
//                  var height = scope.getWindowDimensions();
//                  height = height.h - options.reduce.value;
//                  $.extend(style,{height:height+options.reduce.type,'max-height':height+options.reduce.type});
//                  console.log('style',style)
//                  el.css(style);
////                  el.parent().css(style);
              }
          }

          $rootScope.$on('CalendarFilter:loaded',function(){
              console.log('calendar load');
              scope.$apply();
          });

          w.bind('resize', function () {
              //findMapHeight();
              scope.$apply();
          });
      }
    };
  }
})();



