(function(){})();

;(function(e){e.fn.visible=function(t,n,r){var i=e(this).eq(0),s=i.get(0),o=e(window),u=o.scrollTop(),a=u+o.height(),f=o.scrollLeft(),l=f+o.width(),c=i.offset().top,h=c+i.height(),p=i.offset().left,d=p+i.width(),v=t===true?h:c,m=t===true?c:h,g=t===true?d:p,y=t===true?p:d,b=n===true?s.offsetWidth*s.offsetHeight:true,r=r?r:"both";if(r==="both")return!!b&&m<=a&&v>=u&&y<=l&&g>=f;else if(r==="vertical")return!!b&&m<=a&&v>=u;else if(r==="horizontal")return!!b&&y<=l&&g>=f}})(jQuery);

(function(){

  angular.module('app').directive('infinityScroll',function(){
    return {
      restrict: 'A',
      priority: -1,
      link: function(scope,el,attr){

        var viewport = el.closest('[infinity-scroll-container]');
        var list = viewport.find('[infinity-scroll-items]');

        var viewPortHeight,listHeight,loaderIsVisible;

        var loader = el.find('[loader]').hide();

        var unwatchViewport = scope.$watch(function(){
          return viewport.height();
        },function(vh){
          viewPortHeight = vh;
          setLoader();
        });
        var unwatchList = scope.$watch(function(){
          return list.height();
        },function(lh){
          listHeight = lh;
          setLoader();
        });

        var busy;

        var unwatchLoader = scope.$watch(function(){
          return el.visible();
        },function(visible){
          loaderIsVisible = visible;
          update();
        });

//        if (attr.finish!=undefined) {
//          var finisher = scope.$eval(attr.finish);
//          finisher = function(){
//            loader.hide();
//          };
//        }

        scope.$on('$destroy',function(){
          unwatchViewport();
          unwatchList();
          unwatchLoader();
        });

        function setLoader() {
          if (listHeight > viewPortHeight) {
            loader.show();
          } else {
            loader.hide();
          }
        }

        function update() {
          if (!busy && viewPortHeight>0 && listHeight>0 && listHeight>viewPortHeight && loaderIsVisible) {
            busy = true;
            var promise = scope.$eval(attr.infinityScroll);
            promise.then(function(){
              busy = false;
            });
            if (promise.error) {
              promise.error(loader.hide);
            } else if (promise.catch) {
              promise.catch(loader.hide);
            }
          }
        }
      }
    };
  });

})();
