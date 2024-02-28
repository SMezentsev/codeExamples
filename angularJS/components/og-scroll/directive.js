(function(){
  'use strict';

  angular.module('app')
    .service('$scroll',function(){
      var loc = {
        scrollers:{}
      };
      var pub = {};
      pub.register = function(id,scroller){
        loc.scrollers[id] = _setControl(scroller);
      };

      pub.get = function(id){
        return loc.scrollers[id];
      };
      return pub;

      function _setControl(scroller) {

        scroller.control = {
          saveScrollPosition: function(){
            scroller.saveScrollPosition = scroller.el.scrollTop();
          }
        };

        return scroller;
      }

    })
    .directive('ogScroll',function($scroll,$timeout,$interval){
      return {
        restrict:'A',
        link: function(scope,el,attr){

          var id = el.attr('id')||Math.round(Math.random()*1e10);

          var options = scope.$eval(attr.options)||{
//            cursoropacitymin:1,
//            autohidemode: false
          };
          var options2 = {};


          var firstVisibleChild;


          if (options.onScroll) {
            options2.onScroll = options.onScroll;
            og.del(options,'onScroll');
          }
          if (options.markVisible) {
            options2.markVisible = options.markVisible;
            og.del(options,'markVisible');
          }

          if (options.visibleOffset) {
            options2.visibleOffset = options.visibleOffset;
            og.del(options,'visibleOffset');
          }

          if (options.scrollTopControl) {
            options2.scrollTopControl = options.scrollTopControl;
            og.del(options,'scrollTopControl');
          }

          var ns = el.niceScroll(options);

          var scroller= {
            el: el
          };



          $(window).resize(function(){
            _setHeight(el);
          });

          scope.$watch(function(){
            return el[0].scrollHeight;
          },function(newH,oldH){

            _setHeight(el);

            var data = {
              el: el,
              scrollTop: el.scrollTop(),
              deltaH: newH - oldH
            };

            if (scroller.saveScrollPosition!==undefined) {
              console.log(scroller.saveScrollPosition,data.deltaH);
              scroller.preventReachLoop = true;
              $timeout(function(){
                scroller.preventReachLoop = false;
              },50);
              el.scrollTop(scroller.saveScrollPosition + data.deltaH);
              scroller.saveScrollPosition = undefined;
            }

          });



          $scroll.register(id,scroller);

          $timeout(function(){


            _onScroll();

            if (!ns.noneed) {
              _onContentOverflow(_onScroll);
            } else {
              if (ns && ns.remove)
                ns.remove();
            }

            el.on('scroll.og-scroll',function (e){
              _onScroll(e,true);
            });
            scope.$on('$destroy',function(){
              el.unbind('scroll.og-scroll');
            });


          });

          scope.$on('$destroy',function(){
            if (ns && ns.remove)
              ns.remove();
          });


          var timer,init;
          function _onScroll(e,indeed){

            if (timer) {
              $timeout.cancel(timer);
            }
            timer = $timeout(function(){

              init = true;
              var visibleRange = el.height();

              if (options2.markVisible) {
                var items = el.find(options2.markVisible);
                  items.each(function(i,item){
                    item = $(item);
                    var position = item.offset().top - el.offset().top + (options2.visibleOffset||0) + item.height()/2;
                    if (position>0 && position<visibleRange) {
                      item.addClass('visible');
                    } else {
                      item.removeClass('visible');
                    }
                  });
              }

              if (options2.onScroll) {
                var reachTop = scroller.preventReachLoop ? undefined : indeed && (el.scrollTop()==0);
                var reachBottom = scroller.preventReachLoop ? undefined : indeed && (el.scrollTop()+el.innerHeight()>=el[0].scrollHeight);
                options2.onScroll({
                  el:el,
                  e:e,
                  reachTop: reachTop,
                  reachBottom: reachBottom
                });
              }

            },30);
          }

          function _onContentOverflow(callback) {
            var unwatch = scope.$watch(function(){
              return (el[0].scrollHeight-el[0].offsetHeight);
            },function(over,overOld){
              if (over!=overOld) {
                callback();
                ns.resize();
              }
            });

            scope.$on('$destroy',function(){
              unwatch();
            })
          }
        }
      };
    });

  function _setHeight(el) {
    var container = el.closest('[og-scroll-container]');
    var height = container.height();
    var fixed = el.parent().find('[og-scroll-fixed]');
    if (fixed.length) {
      height -= fixed.outerHeight();
    }
    el.height(height);
  }



})();