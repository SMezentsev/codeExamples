(function(){
  'use strict';

  angular.module('app')

  .directive('ogSearch',function($compile,$timeout,$parse){
    return {
      restrict: 'A',
      terminal: true,
      priority: 1,
      compile: function(ta,attr){

        var id = Math.round(Math.random()*1e10);
        var container = $('<div class="og-search og-dropdown"></div>');

        ta.attr('typeahead',ta.attr('og-search'));
        ta.removeAttr('og-search');

        var options = $parse(attr.options)({})||{};

        if (options.editable!=undefined) {
          ta.attr('typeahead-editable',options.editable);
        }

        if (options.minLength!=undefined) {
          ta.attr('typeahead-min-length',options.minLength);
        }

        if (options.waitMs!=undefined) {
          ta.attr('typeahead-wait-ms',options.waitMs);
        }

        if (options.focusFirst!=undefined) {
          ta.attr('typeahead-focus-first',options.focusFirst);
        }


        ta.wrapAll(container);
        container = ta.parent();
        var loader = $('<ul class="loader dropdown-menu"><li class="dropdown-header"><i loader></i></li></ul>');
        container.append(loader);

        var emptyResultText = options.ifEmpty||'forms.search-if-empty';

        var empty = $('<ul class="empty-result dropdown-menu"><li class="text-warning"><a>'+translate(emptyResultText)+'</a></li></ul>');
        container.append(empty);

        return {

          pre: function(scope,el,attr){
            $timeout(function(){
              ta = $compile(ta[0])(scope);
              loader = $compile(loader[0])(scope);
              empty = $compile(empty[0])(scope);
            });
          },
          post: function(scope,el){
            $timeout(function(){
              var group = el.closest('.form-group');
              var matches = group.find('.matches-list');
              group.addClass('has-feedback');
              container.after('<i class="form-control-feedback fa fa-search"></i>');

              ta.data('on-empty-search',function(){
                empty.removeClass('open');
              });

              ta.data('on-search-start',function(){
                if (matches.hasClass('open')) {
                  loader.addClass('transparent');
                }
                loader.addClass('open');
                empty.removeClass('open');
              });

              ta.data('on-search-end',function(){
                loader.removeClass('open transparent');
              });

              ta.data('on-empty-result',function(){
                empty.addClass('open');
                $(document).click(function(e){
                  empty.removeClass('open');
                });
              });
//
//              el.bind('keydown',function(){
//                container.addClass('open');
//                var val = dd.data().$isolateScope.query;
//                if (val == undefined) {
//                  loader.css({visibility:'hidden',display:'none !important'});
//                } else {
//                  loader.css({visibility:'visible',display:'block'});
//                }
//                dd.parent().css({visibility:'hidden'});
//console.log(dd.data().$isolateScope);
//              });
//
//              dd.data().$isolateScope.$watch('matches',function(m){
//                loader.css({visibility:'hidden',display:'none !important'});
//              });
//
//              dd.scope().$watch('activeIdx',function(empty){
//                if (!empty) {
//                  dd.parent().css('visibility','visible');
//                }
//                loader.css({visibility:'hidden',display:'none !important'});
//                //console.log('loaded, empty=',!!empty);
//              });

            });
          }
        };
      }
    };
  });

})();
